<?php

namespace App\Http\Controllers\Api\Farmer;

use App\Http\Controllers\Controller;
use App\Models\ProductionFactor;
use App\Models\Livestock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

class ProductionFactorController extends Controller
{
    // =================================================================
    // INDEX: List production factors with filters
    // =================================================================
    public function index(Request $request)
    {
        $query = ProductionFactor::whereHas('animal.farmer', fn($q) => $q->where('farmer_id', $request->user()->farmer->id))
            ->with([
                'animal' => fn($q) => $q->select('animal_id', 'tag_number', 'name', 'sex', 'date_of_birth'),
            ])
            ->orderByDesc('calculation_date');

        // Filters
        if ($request->boolean('this_month')) $query->thisMonth();
        if ($request->boolean('last_month')) $query->lastMonth();
        if ($request->boolean('this_year')) $query->thisYear();
        if ($request->boolean('excellent')) $query->excellentEfficiency();
        if ($request->boolean('poor')) $query->poorPerformers();
        if ($request->boolean('loss_makers')) $query->lossMakers();
        if ($request->boolean('top_performers')) $query->topPerformers();
        if ($request->boolean('bonus_eligible')) $query->bonusEligible();
        if ($request->boolean('culling_candidates')) $query->cullingCandidates();

        $factors = $query->paginate(20);

        return response()->json([
            'status' => 'success',
            'data' => $factors
        ]);
    }

    // =================================================================
    // SHOW: Single production factor
    // =================================================================
    public function show(Request $request, $factor_id)
    {
        $factor = ProductionFactor::whereHas('animal.farmer', fn($q) => $q->where('farmer_id', $request->user()->farmer->id))
            ->with(['animal.breed', 'animal.species'])
            ->findOrFail($factor_id);

        return response()->json([
            'status' => 'success',
            'data' => $factor
        ]);
    }

    // =================================================================
    // STORE: Calculate and save production factor
    // =================================================================
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'animal_id' => 'required|exists:livestock,animal_id',
            'period_start' => 'required|date|before:period_end',
            'period_end' => 'required|date|before:tomorrow',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $animal = Livestock::where('animal_id', $request->animal_id)
            ->where('farmer_id', $request->user()->farmer->id)
            ->firstOrFail();

        // Prevent duplicate for same animal + period
        $exists = ProductionFactor::where('animal_id', $request->animal_id)
            ->where('period_start', $request->period_start)
            ->where('period_end', $request->period_end)
            ->exists();

        if ($exists) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tathmini ya uzalishaji tayari ipo kwa kipindi hiki'
            ], 400);
        }

        $factor = DB::transaction(function () use ($request, $animal) {
            // Fetch raw data
            $milk = $animal->milkYields()
                ->whereBetween('yield_date', [$request->period_start, $request->period_end])
                ->selectRaw('SUM(quantity_liters) as total, AVG(quantity_liters) as avg_daily')
                ->first();

            $feed = $animal->feedIntakes()
                ->whereBetween('intake_date', [$request->period_start, $request->period_end])
                ->selectRaw('SUM(quantity) as total_kg, SUM(cost) as total_cost')
                ->first();

            $weight = $animal->weightRecords()
                ->whereBetween('record_date', [$request->period_start, $request->period_end])
                ->orderBy('record_date')
                ->get();

            $weightGain = $weight->count() >= 2
                ? $weight->last()->weight_kg - $weight->first()->weight_kg
                : 0;

            $days = Carbon::parse($request->period_start)->diffInDays($request->period_end) + 1;

            $factor = ProductionFactor::create([
                'animal_id' => $animal->animal_id,
                'calculation_date' => now(),
                'period_start' => $request->period_start,
                'period_end' => $request->period_end,
                'total_feed_consumed_kg' => $feed->total_kg ?? 0,
                'total_milk_produced_liters' => $milk->total ?? 0,
                'weight_gain_kg' => round($weightGain, 2),
                'avg_daily_milk_liters' => $days > 0 ? round(($milk->total ?? 0) / $days, 2) : 0,
                'notes' => $request->notes,
            ]);

            return $factor->fresh();
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Tathmini ya uzalishaji imehifadhiwa',
            'data' => $factor
        ], 201);
    }

    // =================================================================
    // UPDATE: Edit notes or recalculate
    // =================================================================
    public function update(Request $request, $factor_id)
    {
        $factor = ProductionFactor::whereHas('animal.farmer', fn($q) => $q->where('farmer_id', $request->user()->farmer->id))
            ->findOrFail($factor_id);

        $validator = Validator::make($request->all(), [
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $factor->update($request->only('notes'));

        return response()->json([
            'status' => 'success',
            'message' => 'Maelezo yameongezwa',
            'data' => $factor
        ]);
    }

    // =================================================================
    // DESTROY: Delete factor
    // =================================================================
    public function destroy(Request $request, $factor_id)
    {
        $factor = ProductionFactor::whereHas('animal.farmer', fn($q) => $q->where('farmer_id', $request->user()->farmer->id))
            ->findOrFail($factor_id);

        $factor->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Tathmini imefutwa'
        ]);
    }

    // =================================================================
    // SUMMARY: Dashboard KPIs
    // =================================================================
    public function summary(Request $request)
    {
        $farmerId = $request->user()->farmer->id;

        $factors = ProductionFactor::whereHas('animal.farmer', fn($q) => $q->where('farmer_id', $farmerId))
            ->thisMonth()
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'total_cows' => $factors->count(),
                'avg_daily_milk' => round($factors->avg('avg_daily_milk_liters'), 2),
                'avg_fcr' => round($factors->avg('feed_to_milk_ratio'), 3),
                'avg_profit_per_day' => round($factors->avg('profit_per_day'), 0),
                'top_performers' => $factors->where('is_top_performer', true)->count(),
                'loss_makers' => $factors->where('is_loss_making', true)->count(),
                'bonus_eligible' => $factors->where('bonus_eligible', true)->count(),
                'culling_candidates' => ProductionFactor::cullingCandidates()
                    ->whereHas('animal.farmer', fn($q) => $q->where('farmer_id', $farmerId))
                    ->count(),
                'elite_genetics' => $factors->where('genetic_value', 'Elite')->count(),
            ]
        ]);
    }

    // =================================================================
    // ALERTS: Critical performance
    // =================================================================
    public function alerts(Request $request)
    {
        $farmerId = $request->user()->farmer->id;

        $lossMakers = ProductionFactor::lossMakers()
            ->whereHas('animal.farmer', fn($q) => $q->where('farmer_id', $farmerId))
            ->with('animal')
            ->latest('calculation_date')
            ->take(10)
            ->get();

        $culling = ProductionFactor::cullingCandidates()
            ->whereHas('animal.farmer', fn($q) => $q->where('farmer_id', $farmerId))
            ->with('animal')
            ->latest('calculation_date')
            ->take(10)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'loss_makers' => $lossMakers,
                'culling_recommendations' => $culling,
                'total_alerts' => $lossMakers->count() + $culling->count(),
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
                'dairy_cows' => Livestock::where('farmer_id', $farmerId)
                    ->where('species_id', 1)
                    ->where('sex', 'Female')
                    ->where('status', 'Active')
                    ->select('animal_id as value', 'tag_number', 'name')
                    ->orderBy('tag_number')
                    ->get(),
            ]
        ]);
    }

    // =================================================================
    // PDF: Download production factor report
    // =================================================================
    public function downloadPdf(Request $request, $factor_id)
    {
        $factor = ProductionFactor::whereHas('animal.farmer', fn($q) => $q->where('farmer_id', $request->user()->farmer->id))
            ->with(['animal.breed', 'animal.species'])
            ->findOrFail($factor_id);

        $farmer = $request->user()->farmer;

        $pdf = Pdf::loadView('pdf.production-factor', compact('factor', 'farmer'))
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'defaultFont' => 'DejaVu Sans',
                'isHtml5ParserEnabled' => true,
                'isPhpEnabled' => true,
                'isRemoteEnabled' => true,
            ]);

        $filename = "Ripoti-Uzalishaji-{$factor->animal->tag_number}-" .
            Carbon::parse($factor->period_start)->format('M-Y') . ".pdf";

        return $pdf->download($filename);
    }
}
