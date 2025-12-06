<?php

namespace App\Http\Controllers\Api\Farmer;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\Insemination;
use App\Models\HeatCycle;
use App\Models\Livestock;
use App\Models\Semen;
use App\Models\Lactation;
use App\Models\Offspring; // ADDED: Import Offspring model
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BreedingDashboardController extends Controller
{
    public function summary(Request $request)
    {
        $farmerId = $request->user()->farmer->id;

        // --- Common Scoped Queries ---
        $inseminationsQuery = Insemination::whereHas('dam.farmer', fn($q) => $q->where('farmer_id', $farmerId));
        $inseminations = $inseminationsQuery->get();

        $lactations = Lactation::whereHas('dam.farmer', fn($q) => $q->where('farmer_id', $farmerId))->get();

        $semenInventory = Semen::where('farmer_id', $farmerId)->get();

        // --- Offspring Calculation (NEW) ---
        $totalOffspring = Offspring::whereHas('delivery.insemination.dam.farmer', fn($q) => $q->where('farmer_id', $farmerId))->count();

        // --- Insemination Metrics ---
        $totalInseminations = $inseminations->count();
        $pregnantNow = $inseminations->where('is_pregnant', true)->count(); // Assumes 'is_pregnant' is maintained
        $successfulInseminations = $inseminations->where('was_successful', true)->count();

        $successRate = $totalInseminations > 0
            ? round($successfulInseminations / $totalInseminations * 100, 1)
            : 0;

        // --- Lactation Metrics ---
        $activeLactations = $lactations->where('status', 'Ongoing')->count();
        $avgDaysInMilk = $lactations->where('status', 'Ongoing')->avg('days_in_milk');

        // --- Due Soon Calculation (Aligned with 'alerts' logic) ---
        $dueSoonCount = $inseminationsQuery
            ->clone() // Use clone to reuse the base query scope
            ->whereBetween('expected_delivery_date', [now(), now()->addDays(14)])
            ->whereNull('delivery_date') // Ensure delivery hasn't happened
            ->where('is_pregnant', true) // Only count if confirmed pregnant
            ->count();


        return response()->json([
            'status' => 'success',
            'data' => [
                // 1. Insemination & Pregnancy Summary
                'insemination' => [
                    'total_inseminations' => $totalInseminations,
                    'pregnant_now' => $pregnantNow,
                    'success_rate' => $successRate,
                    'ai_vs_natural' => [
                        'AI' => $inseminations->where('breeding_method', 'AI')->count(),
                        'Natural' => $inseminations->where('breeding_method', 'Natural')->count(),
                    ],
                    'due_soon_count' => $dueSoonCount,
                    'avg_calving_interval' => Delivery::whereHas('insemination.dam.farmer', fn($q) => $q->where('farmer_id', $farmerId))
                        ->avg('calving_interval'),
                    'top_sire' => Livestock::where('farmer_id', $farmerId)
                        // Filter by sire sex to ensure correctness
                        ->where('sex', 'Male')
                        ->withCount(['inseminations as successes' => fn($q) => $q->whereHas('delivery')])
                        ->orderByDesc('successes')
                        ->first(['animal_id', 'tag_number', 'name']),
                ],
                // 2. Lactation Summary
                'lactation' => [
                    'total_lactations' => $lactations->count(),
                    'active_lactations' => $activeLactations,
                    'avg_days_in_milk' => round($avgDaysInMilk ?? 0, 0),
                    'avg_peak_milk' => round($lactations->avg('peak_milk_kg') ?? 0, 2),
                ],
                // 3. Inventory Summary
                'inventory' => [
                    'total_semen_straws' => $semenInventory->sum('quantity'),
                    'total_used_straws' => $semenInventory->where('used', true)->sum('quantity'),
                    'total_available_straws' => $semenInventory->where('used', false)->sum('quantity'),
                ],
                // 4. Offspring Summary (NEW BLOCK)
                'offspring' => [
                    'total_offspring_recorded' => $totalOffspring,
                ],
            ]
        ]);
    }

    public function alerts(Request $request)
    {
        $farmerId = $request->user()->farmer->id;

        $dueSoon = Insemination::whereHas('dam.farmer', fn($q) => $q->where('farmer_id', $farmerId))
            // Only look for pregnant animals that have not delivered
            ->where('is_pregnant', true)
            ->whereNull('delivery_date')
            ->whereBetween('expected_delivery_date', [now(), now()->addDays(14)])
            ->with(['dam', 'sire'])
            ->get();

        $heatExpected = HeatCycle::whereHas('dam.farmer', fn($q) => $q->where('farmer_id', $farmerId))
            // Assuming expectedSoon() scope is correctly defined to find animals predicted to come into heat
            ->expectedSoon()
            ->with('dam')
            ->get();

        // Alerts for Dry-off required (if dry off date is in the future but due soon)
        $dryOffAlerts = Lactation::whereHas('dam.farmer', fn($q) => $q->where('farmer_id', $farmerId))
            ->where('status', 'Ongoing')
            ->whereBetween('dry_off_date', [now(), now()->addDays(7)])
            ->with('dam')
            ->get();


        return response()->json([
            'status' => 'success',
            'data' => [
                'due_soon' => $dueSoon,
                'heat_expected' => $heatExpected,
                'dry_off_required' => $dryOffAlerts,
                'total_alerts' => $dueSoon->count() + $heatExpected->count() + $dryOffAlerts->count(),
            ]
        ]);
    }

    public function dropdowns(Request $request)
    {
        $farmerId = $request->user()->farmer->id;

        return response()->json([
            'status' => 'success',
            'data' => [
                // Dams: Active Female Livestock belonging to the farmer
                'dams' => Livestock::where('farmer_id', $farmerId)
                    ->where('sex', 'Female')
                    ->where('status', 'Active')
                    ->select('animal_id as value', DB::raw("CONCAT(tag_number, ' - ', COALESCE(name, 'No Name')) as label"))
                    ->orderBy('tag_number')
                    ->get(),

                // Sires: Active Male Livestock belonging to the farmer
                'sires' => Livestock::where('farmer_id', $farmerId)
                    ->where('sex', 'Male')
                    ->where('status', 'Active')
                    ->select('animal_id as value', DB::raw("CONCAT(tag_number, ' - ', COALESCE(name, 'No Name')) as label"))
                    ->orderBy('tag_number')
                    ->get(),

                // Semen: Available straws belonging to the farmer (FIXED: Added farmer_id scope)
                'semen' => Semen::where('farmer_id', $farmerId)
                    ->where('used', false)
                    ->select('id as value', DB::raw("CONCAT(straw_code, ' - ', bull_name) as label"))
                    ->orderBy('bull_name')
                    ->get(),

                // Heat Cycles: Un-inseminated heat cycles belonging to the farmer
                'heat_cycles' => HeatCycle::whereHas('dam.farmer', fn($q) => $q->where('farmer_id', $farmerId))
                    ->where('inseminated', false)
                    ->select('id as value', DB::raw("CONCAT(dam_id, ' - ', observed_date) as label"))
                    ->get(),

                'breeding_methods' => [
                    ['value' => 'Natural', 'label' => 'Natural Service'],
                    ['value' => 'AI', 'label' => 'Artificial Insemination'],
                ],
                'statuses' => [
                    ['value' => 'Pending', 'label' => 'Pending'],
                    ['value' => 'Confirmed Pregnant', 'label' => 'Pregnant'],
                    ['value' => 'Not Pregnant', 'label' => 'Not Pregnant'],
                    ['value' => 'Delivered', 'label' => 'Delivered'],
                    ['value' => 'Failed', 'label' => 'Failed'],
                ],
            ]
        ]);
    }
}
