<?php

namespace App\Http\Controllers\Api\Farmer;

use App\Http\Controllers\Controller;
use App\Models\Income;
use App\Models\IncomeCategory;
use App\Models\Livestock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class IncomeController extends Controller
{
    // =================================================================
    // INDEX: List incomes with filters
    // =================================================================
    public function index(Request $request)
    {
        $query = Income::where('farmer_id', $request->user()->farmer->id)
            ->with([
                'category' => fn($q) => $q->select('id', 'category_name', 'color_code', 'icon'),
                'animal' => fn($q) => $q->select('animal_id', 'tag_number', 'name'),
                'recordedBy' => fn($q) => $q->select('id', 'firstname', 'lastname')
            ])
            ->latest('income_date');

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
        if ($request->boolean('bonus')) {
            $query->bonus();
        }
        if ($request->boolean('unverified')) {
            $query->unverified();
        }

        $incomes = $query->paginate(20);

        return response()->json([
            'status' => 'success',
            'data' => $incomes
        ]);
    }

    // =================================================================
    // STORE: Record new income
    // =================================================================
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'category_id' => 'required|exists:income_categories,id',
            'animal_id' => 'nullable|exists:livestock,animal_id',
            'quantity' => 'nullable|numeric|min:0',
            'amount' => 'required|numeric|min:0.01',
            'income_date' => 'required|date|before:tomorrow',
            'payment_method' => 'required|in:Cash,M-Pesa,Bank,Cheque,Mobile Money',
            'buyer_customer' => 'nullable|string|max:255',
            'phone_number' => 'nullable|string|max:20',
            'mpesa_transaction_code' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:1000',
            'is_bonus' => 'boolean',
            'bonus_reason' => 'nullable|string|max:500',
            'attachment' => 'nullable|file|mimes:jpg,png,pdf|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $category = IncomeCategory::findOrFail($request->category_id);

        // Security: Animal must belong to farmer
        if ($request->animal_id) {
            Livestock::where('animal_id', $request->animal_id)
                ->where('farmer_id', $request->user()->farmer->id)
                ->firstOrFail();
        }

        // Auto-calculate unit_price if quantity provided
        $unitPrice = $request->quantity > 0 ? round($request->amount / $request->quantity, 2) : null;

        // Use default price if not provided and category has it
        if (!$unitPrice && $category->default_price_per_unit && $request->quantity) {
            $unitPrice = $category->default_price_per_unit;
            $request->merge(['amount' => $unitPrice * $request->quantity]);
        }

        $income = Income::create([
            'farmer_id' => $request->user()->farmer->id,
            'category_id' => $request->category_id,
            'animal_id' => $request->animal_id,
            'amount' => $request->amount,
            'quantity' => $request->quantity,
            'unit_price' => $unitPrice,
            'income_date' => $request->income_date,
            'payment_method' => $request->payment_method,
            'buyer_customer' => $request->buyer_customer,
            'phone_number' => $request->phone_number,
            'mpesa_transaction_code' => $request->mpesa_transaction_code,
            'description' => $request->description,
            'is_bonus' => $request->boolean('is_bonus', false),
            'bonus_reason' => $request->is_bonus ? $request->bonus_reason : null,
            'recorded_by' => $request->user()->id,
            'status' => 'Pending',
        ]);

        // Handle file upload
        if ($request->hasFile('attachment')) {
            $path = $request->file('attachment')->store('incomes', 'public');
            $income->attachments()->create([
                'file_path' => $path,
                'file_type' => $request->file('attachment')->getClientMimeType(),
                'file_name' => $request->file('attachment')->getClientOriginalName(),
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Mapato yameandikishwa kikamilifu',
            'data' => $income->load('category', 'animal')
        ], 201);
    }

    // =================================================================
    // SHOW: Single income
    // =================================================================
    public function show(Request $request, $income_id)
    {
        $income = Income::where('farmer_id', $request->user()->farmer->id)
            ->with(['category', 'animal', 'recordedBy', 'attachments', 'milkYield'])
            ->findOrFail($income_id);

        return response()->json(['status' => 'success', 'data' => $income]);
    }

    // =================================================================
    // UPDATE: Edit income
    // =================================================================
    public function update(Request $request, $income_id)
    {
        $income = Income::where('farmer_id', $request->user()->farmer->id)
            ->findOrFail($income_id);

        $validator = Validator::make($request->all(), [
            'category_id' => 'sometimes|exists:income_categories,id',
            'animal_id' => 'nullable|exists:livestock,animal_id',
            'amount' => 'sometimes|numeric|min:0.01',
            'quantity' => 'nullable|numeric|min:0',
            'income_date' => 'sometimes|date|before:tomorrow',
            'status' => 'sometimes|in:Pending,Verified,Rejected',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $income->update($request->only([
            'category_id', 'animal_id', 'amount', 'quantity', 'income_date',
            'payment_method', 'buyer_customer', 'phone_number',
            'mpesa_transaction_code', 'description', 'status'
        ]));

        return response()->json([
            'status' => 'success',
            'message' => 'Mapato yamesasishwa',
            'data' => $income
        ]);
    }

    // =================================================================
    // DESTROY: Delete income
    // =================================================================
    public function destroy(Request $request, $income_id)
    {
        $income = Income::where('farmer_id', $request->user()->farmer->id)
            ->findOrFail($income_id);

        $income->delete();

        return response()->json(['status' => 'success', 'message' => 'Mapato yamefutwa']);
    }

    // =================================================================
    // SUMMARY: Dashboard KPIs
    // =================================================================
    public function summary(Request $request)
    {
        $farmerId = $request->user()->farmer->id;

        $totalThisMonth = Income::where('farmer_id', $farmerId)->thisMonth()->sum('amount');
        $totalThisYear = Income::where('farmer_id', $farmerId)->thisYear()->sum('amount');
        $milkIncome = Income::where('farmer_id', $farmerId)->milkSales()->thisMonth()->sum('amount');
        $topCategory = IncomeCategory::withSum('incomes', 'amount')
            ->whereHas('incomes', fn($q) => $q->where('farmer_id', $farmerId))
            ->orderByDesc('incomes_sum_amount')
            ->first(['id', 'category_name', 'color_code']);

        return response()->json([
            'status' => 'success',
            'data' => [
                'total_this_month' => round($totalThisMonth, 2),
                'total_this_year' => round($totalThisYear, 2),
                'milk_income_this_month' => round($milkIncome, 2),
                'top_revenue_source' => $topCategory?->category_name ?? 'Hapana',
                'bonus_earned' => Income::where('farmer_id', $farmerId)->bonus()->sum('amount'),
                'unverified_count' => Income::where('farmer_id', $farmerId)->unverified()->count(),
            ]
        ]);
    }

    // =================================================================
    // DROPDOWNS: For income form
    // =================================================================
    public function dropdowns(Request $request)
    {
        $farmerId = $request->user()->farmer->id;

        return response()->json([
            'status' => 'success',
            'data' => [
                'categories' => IncomeCategory::active()
                    ->select('id as value', 'category_name as label', 'color_code', 'icon', 'default_price_per_unit', 'unit_of_measure')
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
                    ['value' => 'Bank', 'label' => 'Benki'],
                    ['value' => 'Cheque', 'label' => 'Hundi'],
                    ['value' => 'Mobile Money', 'label' => 'Pesa za Simu'],
                ]
            ]
        ]);
    }

    // =================================================================
    // ALERTS: Unverified, bonus due
    // =================================================================
    public function alerts(Request $request)
    {
        $farmerId = $request->user()->farmer->id;

        $unverified = Income::where('farmer_id', $farmerId)
            ->unverified()
            ->with('category')
            ->get();

        $highGrowth = IncomeCategory::highGrowth()
            ->whereHas('incomes', fn($q) => $q->where('farmer_id', $farmerId))
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'unverified' => $unverified,
                'high_growth_categories' => $highGrowth,
                'total_alerts' => $unverified->count() + $highGrowth->count()
            ]
        ]);
    }

    // =================================================================
    // PDF: Download single income receipt
    // =================================================================
    public function downloadPdf(Request $request, $income_id)
    {
        $income = Income::where('farmer_id', $request->user()->farmer->id)
            ->with(['category', 'animal', 'recordedBy'])
            ->findOrFail($income_id);

        $farmer = $request->user()->farmer;

        $pdf = Pdf::loadView('pdf.income-receipt', compact('income', 'farmer'))
            ->setPaper('a4', 'portrait')
            ->setOptions(['defaultFont' => 'DejaVu Sans']);

        $filename = "Risiti-Mapato-{$income->category->category_name}-" .
            Carbon::parse($income->income_date)->format('d-m-Y') . ".pdf";

        return $pdf->download($filename);
    }

    // =================================================================
    // PDF: Category summary report
    // =================================================================
    public function categoryReportPdf(Request $request, $category_id)
    {
        $category = IncomeCategory::whereHas('incomes', fn($q) => $q->where('farmer_id', $request->user()->farmer->id))
            ->with(['incomes' => fn($q) => $q->thisYear()->with('animal')])
            ->findOrFail($category_id);

        $farmer = $request->user()->farmer;

        $pdf = Pdf::loadView('pdf.income-category-report', compact('category', 'farmer'))
            ->setPaper('a4', 'portrait')
            ->setOptions(['defaultFont' => 'DejaVu Sans']);

        $filename = "Ripoti-Mapato-{$category->category_name}-" . now()->format('Y') . ".pdf";

        return $pdf->download($filename);
    }
}
