<?php

namespace App\Http\Controllers\Api\Farmer;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\Livestock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SaleController extends Controller
{
    public function index(Request $request)
    {
        $query = Sale::where('farmer_id', $request->user()->farmer->id)
            ->with(['animal' => fn($q) => $q->select('animal_id', 'tag_number', 'name')])
            ->latest('sale_date');

        if ($request->filled('type')) $query->byType($request->type);
        if ($request->boolean('this_month')) $query->thisMonth();

        $sales = $query->paginate(20);

        return response()->json([
            'status' => 'success',
            'data' => $sales
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'sale_type' => 'required|in:Animal,Milk,Other',
            'animal_id' => 'nullable|exists:livestock,animal_id',
            'buyer_name' => 'required|string|max:255',
            'quantity' => 'nullable|numeric|min:0',
            'unit' => 'nullable|string|max:20',
            'unit_price' => 'required|numeric|min:0',
            'sale_date' => 'required|date|before:tomorrow',
            'payment_method' => 'required|in:Cash,M-Pesa,Bank,Cheque',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        if ($request->sale_type === 'Animal' && !$request->animal_id) {
            return response()->json(['status' => 'error', 'message' => 'Animal required for animal sale'], 422);
        }

        if ($request->animal_id) {
            Livestock::where('animal_id', $request->animal_id)
                ->where('farmer_id', $request->user()->farmer->id)
                ->firstOrFail();
        }

        $total = $request->quantity ? $request->quantity * $request->unit_price : $request->unit_price;

        $sale = Sale::create([
            'farmer_id' => $request->user()->farmer->id,
            'animal_id' => $request->animal_id,
            'sale_type' => $request->sale_type,
            'buyer_name' => $request->buyer_name,
            'buyer_phone' => $request->buyer_phone,
            'buyer_location' => $request->buyer_location,
            'quantity' => $request->quantity,
            'unit' => $request->unit,
            'unit_price' => $request->unit_price,
            'total_amount' => $total,
            'sale_date' => $request->sale_date,
            'payment_method' => $request->payment_method,
            'receipt_number' => $request->receipt_number,
            'notes' => $request->notes,
            'status' => 'Completed',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Muuzaji ameandikishwa',
            'data' => $sale->load('animal')
        ], 201);
    }

    public function dropdowns(Request $request)
    {
        $farmerId = $request->user()->farmer->id;

        return response()->json([
            'status' => 'success',
            'data' => [
                'animals' => Livestock::where('farmer_id', $farmerId)
                    ->active()
                    ->select('animal_id as value', DB::raw("CONCAT(tag_number, ' - ', COALESCE(name, 'Hapana')) as label"))
                    ->orderBy('tag_number')
                    ->get(),
                'sale_types' => [
                    ['value' => 'Animal', 'label' => 'Mnyama'],
                    ['value' => 'Milk', 'label' => 'Maziwa'],
                    ['value' => 'Other', 'label' => 'Mengine'],
                ],
                'payment_methods' => [
                    ['value' => 'Cash', 'label' => 'Pesa Taslimu'],
                    ['value' => 'M-Pesa', 'label' => 'M-Pesa'],
                    ['value' => 'Bank', 'label' => 'Benki'],
                    ['value' => 'Cheque', 'label' => 'Hundi'],
                ]
            ]
        ]);
    }

    public function downloadPdf(Request $request, $sale_id)
    {
        $sale = Sale::where('farmer_id', $request->user()->farmer->id)
            ->with('animal')
            ->findOrFail($sale_id);

        $farmer = $request->user()->farmer;

        $pdf = Pdf::loadView('pdf.sale-receipt', compact('sale', 'farmer'))
            ->setPaper('a4', 'portrait')
            ->setOptions(['defaultFont' => 'DejaVu Sans']);

        $filename = "Risiti-Muuzaji-{$sale->sale_type}-" . $sale->sale_date->format('d-m-Y') . ".pdf";

        return $pdf->download($filename);
    }
}
