<?php

namespace App\Http\Controllers\Api\Farmer;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Livestock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class ExpenseController extends Controller
{
    // =================================================================
    // INDEX: List expenses with filters
    // =================================================================
    public function index(Request $request)
    {
        $query = Expense::where('farmer_id', $request->user()->farmer->id)
            ->with([
                'category' => fn($q) => $q->select('category_id', 'category_name', 'color_code', 'icon'),
                'animal' => fn($q) => $q->select('animal_id', 'tag_number', 'name'),
                'recordedBy' => fn($q) => $q->select('id', 'firstname', 'lastname')
            ])
            ->latest('expense_date');

        // Filters
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }
        if ($request->filled('animal_id')) {
            $query->forAnimal($request->animal_id);
        }
        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }
        if ($request->boolean('this_month')) {
            $query->thisMonth();
        }
        if ($request->boolean('over_budget')) {
            $query->whereHas('category', fn($q) => $q->overBudget());
        }
        if ($request->boolean('recurring')) {
            $query->where('is_recurring', true);
        }
        if ($request->boolean('unapproved')) {
            $query->unapproved();
        }

        $expenses = $query->paginate(20);

        return response()->json([
            'status' => 'success',
            'data' => $expenses
        ]);
    }

    // =================================================================
    // STORE: Record new expense
    // =================================================================
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'category_id' => 'required|exists:expense_categories,category_id',
            'animal_id' => 'nullable|exists:livestock,animal_id',
            'amount' => 'required|numeric|min:0.01',
            'expense_date' => 'required|date|before:tomorrow',
            'payment_method' => 'required|in:Cash,M-Pesa,Bank Transfer,Cheque,Credit',
            'vendor_supplier' => 'nullable|string|max:255',
            'receipt_number' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:1000',
            'is_recurring' => 'boolean',
            'recurring_frequency' => 'nullable|in:monthly,quarterly,yearly',
            'attachment' => 'nullable|file|mimes:jpg,png,pdf|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        // Security: Animal must belong to farmer
        if ($request->animal_id) {
            Livestock::where('animal_id', $request->animal_id)
                ->where('farmer_id', $request->user()->farmer->id)
                ->firstOrFail();
        }

        $expense = Expense::create([
            'farmer_id' => $request->user()->farmer->id,
            'category_id' => $request->category_id,
            'animal_id' => $request->animal_id,
            'amount' => $request->amount,
            'expense_date' => $request->expense_date,
            'payment_method' => $request->payment_method,
            'vendor_supplier' => $request->vendor_supplier,
            'receipt_number' => $request->receipt_number,
            'description' => $request->description,
            'is_recurring' => $request->boolean('is_recurring', false),
            'recurring_frequency' => $request->is_recurring ? $request->recurring_frequency : null,
            'recorded_by' => $request->user()->id,
            'status' => 'Posted',
        ]);

        // Handle file upload
        if ($request->hasFile('attachment')) {
            $path = $request->file('attachment')->store('expenses', 'public');
            $expense->attachments()->create([
                'file_path' => $path,
                'file_type' => $request->file('attachment')->getClientMimeType(),
                'file_name' => $request->file('attachment')->getClientOriginalName(),
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Gharama imeandikishwa kikamilifu',
            'data' => $expense->load('category', 'animal')
        ], 201);
    }

    // =================================================================
    // SHOW: Single expense
    // =================================================================
    public function show(Request $request, $expense_id)
    {
        $expense = Expense::where('farmer_id', $request->user()->farmer->id)
            ->with(['category', 'animal', 'recordedBy', 'attachments'])
            ->findOrFail($expense_id);

        return response()->json(['status' => 'success', 'data' => $expense]);
    }

    // =================================================================
    // UPDATE: Edit expense
    // =================================================================
    public function update(Request $request, $expense_id)
    {
        $expense = Expense::where('farmer_id', $request->user()->farmer->id)
            ->findOrFail($expense_id);

        $validator = Validator::make($request->all(), [
            'category_id' => 'sometimes|exists:expense_categories,category_id',
            'animal_id' => 'nullable|exists:livestock,animal_id',
            'amount' => 'sometimes|numeric|min:0.01',
            'expense_date' => 'sometimes|date|before:tomorrow',
            'payment_method' => 'sometimes|in:Cash,M-Pesa,Bank Transfer,Cheque,Credit',
            'status' => 'sometimes|in:Posted,Approved,Rejected',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $expense->update($request->only([
            'category_id', 'animal_id', 'amount', 'expense_date',
            'payment_method', 'vendor_supplier', 'receipt_number',
            'description', 'status'
        ]));

        return response()->json([
            'status' => 'success',
            'message' => 'Gharama imesasishwa',
            'data' => $expense
        ]);
    }

    // =================================================================
    // DESTROY: Delete expense
    // =================================================================
    public function destroy(Request $request, $expense_id)
    {
        $expense = Expense::where('farmer_id', $request->user()->farmer->id)
            ->findOrFail($expense_id);

        $expense->delete();

        return response()->json(['status' => 'success', 'message' => 'Gharama imefutwa']);
    }

    // =================================================================
    // SUMMARY: Dashboard KPIs
    // =================================================================
    public function summary(Request $request)
    {
        $farmerId = $request->user()->farmer->id;

        $totalThisMonth = Expense::where('farmer_id', $farmerId)->thisMonth()->sum('amount');
        $totalThisYear = Expense::where('farmer_id', $farmerId)->thisYear()->sum('amount');
        $overBudget = ExpenseCategory::overBudget()->whereHas('expenses', fn($q) => $q->where('farmer_id', $farmerId))->count();
        $recurringDue = Expense::where('farmer_id', $farmerId)->overdue()->count();

        return response()->json([
            'status' => 'success',
            'data' => [
                'total_this_month' => round($totalThisMonth, 2),
                'total_this_year' => round($totalThisYear, 2),
                'categories_over_budget' => $overBudget,
                'recurring_due_soon' => $recurringDue,
                'top_category' => ExpenseCategory::withSum('expenses', 'amount')
                    ->whereHas('expenses', fn($q) => $q->where('farmer_id', $farmerId))
                    ->orderByDesc('expenses_sum_amount')
                    ->first(['category_id', 'category_name', 'color_code'])
            ]
        ]);
    }

    // =================================================================
    // DROPDOWNS: For expense form
    // =================================================================
    public function dropdowns(Request $request)
    {
        $farmerId = $request->user()->farmer->id;

        return response()->json([
            'status' => 'success',
            'data' => [
                'categories' => ExpenseCategory::select('category_id as value', 'category_name as label', 'color_code', 'icon')
                    ->orderBy('sort_order')
                    ->get(),
                'animals' => Livestock::where('farmer_id', $farmerId)
                    ->active()
                    ->select('animal_id as value', DB::raw("CONCAT(tag_number, ' - ', COALESCE(name, 'Hapana Jina')) as label"))
                    ->orderBy('tag_number')
                    ->get(),
                'payment_methods' => [
                    ['value' => 'Cash', 'label' => 'Pesa Taslimu'],
                    ['value' => 'M-Pesa', 'label' => 'M-Pesa'],
                    ['value' => 'Bank Transfer', 'label' => 'Benki'],
                    ['value' => 'Cheque', 'label' => 'Hundi'],
                    ['value' => 'Credit', 'label' => 'Mkopo'],
                ],
                'frequencies' => [
                    ['value' => 'monthly', 'label' => 'Kila Mwezi'],
                    ['value' => 'quarterly', 'label' => 'Kila Robo Mwaka'],
                    ['value' => 'yearly', 'label' => 'Kila Mwaka'],
                ]
            ]
        ]);
    }

    // =================================================================
    // ALERTS: Over budget, recurring due
    // =================================================================
    public function alerts(Request $request)
    {
        $farmerId = $request->user()->farmer->id;

        $overBudget = ExpenseCategory::overBudget()
            ->whereHas('expenses', fn($q) => $q->where('farmer_id', $farmerId))
            ->with(['expenses' => fn($q) => $q->thisMonth()])
            ->get();

        $recurringDue = Expense::where('farmer_id', $farmerId)
            ->overdue()
            ->with('category')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'over_budget' => $overBudget,
                'recurring_due' => $recurringDue,
                'total_alerts' => $overBudget->count() + $recurringDue->count()
            ]
        ]);
    }

    // =================================================================
    // PDF: Download single expense receipt
    // =================================================================
    public function downloadPdf(Request $request, $expense_id)
    {
        $expense = Expense::where('farmer_id', $request->user()->farmer->id)
            ->with(['category', 'animal', 'recordedBy'])
            ->findOrFail($expense_id);

        $farmer = $request->user()->farmer;

        $pdf = Pdf::loadView('pdf.expense-receipt', compact('expense', 'farmer'))
            ->setPaper('a4', 'portrait')
            ->setOptions(['defaultFont' => 'DejaVu Sans']);

        $filename = "Risiti-Gharama-{$expense->category->category_name}-" .
            Carbon::parse($expense->expense_date)->format('d-m-Y') . ".pdf";

        return $pdf->download($filename);
    }

    // =================================================================
    // PDF: Category summary report
    // =================================================================
    public function categoryReportPdf(Request $request, $category_id)
    {
        $category = ExpenseCategory::whereHas('expenses', fn($q) => $q->where('farmer_id', $request->user()->farmer->id))
            ->with(['expenses' => fn($q) => $q->thisYear()->with('animal')])
            ->findOrFail($category_id);

        $farmer = $request->user()->farmer;

        $pdf = Pdf::loadView('pdf.expense-category-report', compact('category', 'farmer'))
            ->setPaper('a4', 'portrait')
            ->setOptions(['defaultFont' => 'DejaVu Sans']);

        $filename = "Ripoti-Gharama-{$category->category_name}-" . now()->format('Y') . ".pdf";

        return $pdf->download($filename);
    }
}
