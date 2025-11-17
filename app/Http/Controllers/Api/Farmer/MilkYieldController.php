<?php

namespace App\Http\Controllers\Api\Farmer;

use App\Http\Controllers\Controller;
use App\Models\MilkYieldRecord;
use App\Models\Livestock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

class MilkYieldController extends Controller
{
    // =================================================================
    // INDEX: List milk yields with filters
    // =================================================================
    public function index(Request $request)
    {
        $query = MilkYieldRecord::whereHas('animal.farmer', fn($q) => $q->where('farmer_id', $request->user()->farmer->id))
            ->with([
                'animal' => fn($q) => $q->select('animal_id', 'tag_number', 'name', 'sex'),
            ])
            ->withCount(['income as income_today' => fn($q) => $q->whereDate('income_date', today())])
            ->orderByDesc('yield_date')
            ->orderBy('milking_session');

        // Filters
        if ($request->boolean('today')) $query->today();
        if ($request->boolean('this_week')) $query->thisWeek();
        if ($request->boolean('this_month')) $query->thisMonth();
        if ($request->filled('animal_id')) $query->forCow($request->animal_id);
        if ($request->boolean('morning')) $query->morning();
        if ($request->boolean('evening')) $query->evening();
        if ($request->boolean('rejected')) $query->rejected();
        if ($request->boolean('high_scc')) $query->highScc();
        if ($request->boolean('mastitis_risk')) $query->mastitisRisk();
        if ($request->boolean('low_yield')) $query->lowYield(12);
        if ($request->boolean('high_yield')) $query->highYield(28);
        if ($request->boolean('peak')) $query->peakPerformers();

        $yields = $query->paginate(20);

        return response()->json([
            'status' => 'success',
            'data' => $yields
        ]);
    }

    // =================================================================
    // SHOW: Single milk yield
    // =================================================================
    public function show(Request $request, $yield_id)
    {
        $yield = MilkYieldRecord::whereHas('animal.farmer', fn($q) => $q->where('farmer_id', $request->user()->farmer->id))
            ->with(['animal.breed', 'animal.species', 'income'])
            ->findOrFail($yield_id);

        return response()->json([
            'status' => 'success',
            'data' => $yield
        ]);
    }

    // =================================================================
    // STORE: Record new milk yield
    // =================================================================
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'animal_id' => 'required|exists:livestock,animal_id',
            'yield_date' => 'required|date|before:tomorrow',
            'milking_session' => 'required|in:Morning,Midday,Evening',
            'quantity_liters' => 'required|numeric|min:0.1|max:100',
            'quality_grade' => 'required|in:A,B,C,Rejected',
            'fat_content' => 'nullable|numeric|min:0|max:10',
            'protein_content' => 'nullable|numeric|min:0|max:10',
            'somatic_cell_count' => 'nullable|integer|min:0',
            'temperature' => 'nullable|numeric|min:30|max:45',
            'conductivity' => 'nullable|numeric|min:0|max:10',
            'collection_center' => 'nullable|string|max:100',
            'collector_name' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Security check
        $animal = Livestock::where('animal_id', $request->animal_id)
            ->where('farmer_id', $request->user()->farmer->id)
            ->firstOrFail();

        // Prevent duplicate session on same day
        $exists = MilkYieldRecord::where('animal_id', $request->animal_id)
            ->where('yield_date', $request->yield_date)
            ->where('milking_session', $request->milking_session)
            ->exists();

        if ($exists) {
            return response()->json([
                'status' => 'error',
                'message' => 'Rekodi ya maziwa tayari ipo kwa kipindi hiki'
            ], 400);
        }

        $yield = MilkYieldRecord::create($request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'Maziwa yameandikishwa kikamilifu',
            'data' => $yield->load('animal')
        ], 201);
    }

    // =================================================================
    // UPDATE: Edit milk yield
    // =================================================================
    public function update(Request $request, $yield_id)
    {
        $yield = MilkYieldRecord::whereHas('animal.farmer', fn($q) => $q->where('farmer_id', $request->user()->farmer->id))
            ->findOrFail($yield_id);

        $validator = Validator::make($request->all(), [
            'quantity_liters' => 'sometimes|numeric|min:0.1|max:100',
            'quality_grade' => 'sometimes|in:A,B,C,Rejected',
            'fat_content' => 'nullable|numeric|min:0|max:10',
            'protein_content' => 'nullable|numeric|min:0|max:10',
            'somatic_cell_count' => 'nullable|integer|min:0',
            'temperature' => 'nullable|numeric|min:30|max:45',
            'conductivity' => 'nullable|numeric|min:0|max:10',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $yield->update($request->only([
            'quantity_liters', 'quality_grade', 'fat_content', 'protein_content',
            'somatic_cell_count', 'temperature', 'conductivity', 'notes'
        ]));

        return response()->json([
            'status' => 'success',
            'message' => 'Rekodi ya maziwa imesasishwa',
            'data' => $yield->fresh(['animal'])
        ]);
    }

    // =================================================================
    // DESTROY: Delete milk yield
    // =================================================================
    public function destroy(Request $request, $yield_id)
    {
        $yield = MilkYieldRecord::whereHas('animal.farmer', fn($q) => $q->where('farmer_id', $request->user()->farmer->id))
            ->findOrFail($yield_id);

        $yield->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Rekodi ya maziwa imefutwa'
        ]);
    }

    // =================================================================
    // SUMMARY: Dashboard KPIs
    // =================================================================
    public function summary(Request $request)
    {
        $farmerId = $request->user()->farmer->id;
        $today = today();
        $thisMonth = now()->format('Y-m');

        $yields = MilkYieldRecord::whereHas('animal.farmer', fn($q) => $q->where('farmer_id', $farmerId))
            ->thisMonth()
            ->get();

        $totalLiters = $yields->sum('quantity_liters');
        $totalIncome = $yields->sum('actual_income');
        $avgPerCow = Livestock::where('farmer_id', $farmerId)
            ->where('status', 'Lactating')
            ->count() > 0 ? round($totalLiters / Livestock::where('farmer_id', $farmerId)->where('status', 'Lactating')->count(), 2) : 0;

        return response()->json([
            'status' => 'success',
            'data' => [
                'total_liters' => round($totalLiters, 2),
                'total_income_tzs' => round($totalIncome, 0),
                'avg_per_cow' => $avgPerCow,
                'grade_a' => $yields->where('quality_grade', 'A')->sum('quantity_liters'),
                'rejected' => $yields->where('quality_grade', 'Rejected')->sum('quantity_liters'),
                'high_scc' => $yields->where('is_high_scc', true)->count(),
                'mastitis_risk' => MilkYieldRecord::mastitisRisk()
                    ->whereHas('animal.farmer', fn($q) => $q->where('farmer_id', $farmerId))
                    ->count(),
                'peak_cows' => MilkYieldRecord::peakPerformers()
                    ->whereHas('animal.farmer', fn($q) => $q->where('farmer_id', $farmerId))
                    ->count(),
                'today_liters' => MilkYieldRecord::whereHas('animal.farmer', fn($q) => $q->where('farmer_id', $farmerId))
                    ->whereDate('yield_date', $today)
                    ->sum('quantity_liters'),
            ]
        ]);
    }

    // =================================================================
    // ALERTS: Critical issues
    // =================================================================
    public function alerts(Request $request)
    {
        $farmerId = $request->user()->farmer->id;

        $mastitis = MilkYieldRecord::mastitisRisk()
            ->whereHas('animal.farmer', fn($q) => $q->where('farmer_id', $farmerId))
            ->with('animal')
            ->latest('yield_date')
            ->take(10)
            ->get();

        $rejected = MilkYieldRecord::rejected()
            ->whereHas('animal.farmer', fn($q) => $q->where('farmer_id', $farmerId))
            ->with('animal')
            ->latest('yield_date')
            ->take(10)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'mastitis_risk' => $mastitis,
                'rejected_milk' => $rejected,
                'total_alerts' => $mastitis->count() + $rejected->count(),
            ]
        ]);
    }

    // =================================================================
    // DROPDOWNS: For form
    // =================================================================
    public function dropdowns(Request $request)
    {
        $farmerId = $request->user()->farmer->id;

        return response()->json([
            'status' => 'success',
            'data' => [
                'animals' => Livestock::where('farmer_id', $farmerId)
                    ->whereIn('status', ['Active', 'Lactating'])
                    ->select('animal_id as value', 'tag_number', 'name')
                    ->orderBy('tag_number')
                    ->get(),
                'sessions' => [
                    ['value' => 'Morning', 'label' => 'Asubuhi'],
                    ['value' => 'Midday', 'label' => 'Mchana'],
                    ['value' => 'Evening', 'label' => 'Jioni'],
                ],
                'grades' => [
                    ['value' => 'A', 'label' => 'A - Bora'],
                    ['value' => 'B', 'label' => 'B - Wastani'],
                    ['value' => 'C', 'label' => 'C - Chini'],
                    ['value' => 'Rejected', 'label' => 'Imekataliwa'],
                ],
            ]
        ]);
    }

    // =================================================================
    // PDF: Download single yield report
    // =================================================================
    public function downloadPdf(Request $request, $yield_id)
    {
        $yield = MilkYieldRecord::whereHas('animal.farmer', fn($q) => $q->where('farmer_id', $request->user()->farmer->id))
            ->with(['animal.breed', 'animal.species', 'income'])
            ->findOrFail($yield_id);

        $farmer = $request->user()->farmer;

        $pdf = Pdf::loadView('pdf.milk-yield', compact('yield', 'farmer'))
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'defaultFont' => 'DejaVu Sans',
                'isHtml5ParserEnabled' => true,
                'isPhpEnabled' => true,
                'isRemoteEnabled' => true,
            ]);

        $filename = "Ripoti-Maziwa-{$yield->animal->tag_number}-" .
            Carbon::parse($yield->yield_date)->format('d-m-Y') . ".pdf";

        return $pdf->download($filename);
    }
}
