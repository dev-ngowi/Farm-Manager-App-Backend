<?php

namespace App\Http\Controllers\Api\Farmer;

use App\Http\Controllers\Controller;
use App\Models\BreedingRecord;
use App\Models\Livestock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BreedingController extends Controller
{
    // =================================================================
    // INDEX: List all breeding records for logged-in farmer
    // =================================================================
    public function index(Request $request)
    {
        $query = BreedingRecord::query()
            ->whereHas('dam.farmer', fn($q) => $q->where('farmer_id', $request->user()->farmer->id))
            ->with([
                'dam' => fn($q) => $q->select('animal_id', 'tag_number', 'name', 'species_id'),
                'sire' => fn($q) => $q->select('animal_id', 'tag_number', 'name'),
                'birthRecord',
                'latestCheck'
            ])
            ->withCount([
                'offspringRecords as live_births' => fn($q) => $q->where('health_status', '!=', 'Deceased')
            ])
            ->select('id', 'dam_id', 'sire_id', 'breeding_type', 'breeding_date', 'expected_delivery_date', 'status', 'notes');

        // Apply filters directly on BreedingRecord (much faster)
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('breeding_type')) {
            $query->where('breeding_type', $request->breeding_type);
        }
        if ($request->boolean('pregnant')) {
            $query->pregnant();
        }
        if ($request->boolean('due_soon')) {
            $query->dueSoon(14);
        }
        if ($request->boolean('overdue')) {
            $query->overdue();
        }
        if ($request->filled('sire_id')) {
            $query->where('sire_id', $request->sire_id);
        }

        $breedings = $query->get();

        // Meta can still be calculated from collection (or better: use DB aggregates)
        return response()->json([
            'status' => 'success',
            'data' => $breedings,
            'meta' => [
                'total' => $breedings->count(),
                'pregnant' => $breedings->where('status', 'Confirmed Pregnant')->count(),
                'due_soon' => $breedings->where('days_to_delivery', '>', 0)->where('days_to_delivery', '<=', 14)->count(),
                'overdue' => $breedings->where('is_overdue', true)->count(),
            ]
        ]);
    }

    // =================================================================
    // SHOW: Single breeding record
    // =================================================================
    public function show(Request $request, $breeding_id)
    {
        $breeding = BreedingRecord::whereHas('dam.farmer', fn($q) => $q->where('farmer_id', $request->user()->farmer->id))
            ->with([
                'dam' => fn($q) => $q->select('animal_id', 'tag_number', 'name', 'species_id'),
                'sire' => fn($q) => $q->select('animal_id', 'tag_number', 'name'),
                'birthRecord.offspringRecords.offspring',
                'pregnancyChecks' => fn($q) => $q->orderByDesc('check_date'),
                'offspring' => fn($q) => $q->select('animal_id', 'tag_number', 'name', 'sex', 'date_of_birth')
            ])
            ->withCount(['offspringRecords as total_offspring', 'offspringRecords as live_births' => fn($q) => $q->where('health_status', '!=', 'Deceased')])
            ->findOrFail($breeding_id);

        return response()->json([
            'status' => 'success',
            'data' => $breeding
        ]);
    }

    // =================================================================
    // STORE: Record new breeding (AI or Natural)
    // =================================================================
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'dam_id' => 'required|exists:livestock,animal_id',
            'sire_id' => 'nullable|exists:livestock,animal_id',
            'breeding_type' => 'required|in:AI,Natural',
            'ai_semen_code' => 'nullable|string|max:50',
            'ai_bull_name' => 'nullable|string|max:100',
            'breeding_date' => 'required|date|before:tomorrow',
            'expected_delivery_date' => 'required|date|after:breeding_date',
            'status' => 'required|in:Pending,Confirmed Pregnant,Failed',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Security: Ensure dam belongs to farmer
        $dam = Livestock::where('animal_id', $request->dam_id)
            ->where('farmer_id', $request->user()->farmer->id)
            ->firstOrFail();

        if ($request->sire_id) {
            $sire = Livestock::where('animal_id', $request->sire_id)
                ->where('farmer_id', $request->user()->farmer->id)
                ->firstOrFail();
        }

        $breeding = BreedingRecord::create([
            'dam_id' => $request->dam_id,
            'sire_id' => $request->sire_id,
            'breeding_type' => $request->breeding_type,
            'ai_semen_code' => $request->breeding_type === 'AI' ? $request->ai_semen_code : null,
            'ai_bull_name' => $request->breeding_type === 'AI' ? $request->ai_bull_name : null,
            'breeding_date' => $request->breeding_date,
            'expected_delivery_date' => $request->expected_delivery_date,
            'status' => $request->status,
            'notes' => $request->notes,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Breeding recorded successfully',
            'data' => $breeding->load(['dam', 'sire'])
        ], 201);
    }

    // =================================================================
    // UPDATE: Edit breeding record
    // =================================================================
    public function update(Request $request, $breeding_id)
    {
        $breeding = BreedingRecord::whereHas('dam.farmer', fn($q) => $q->where('farmer_id', $request->user()->farmer->id))
            ->findOrFail($breeding_id);

        $validator = Validator::make($request->all(), [
            'sire_id' => 'nullable|exists:livestock,animal_id',
            'ai_semen_code' => 'nullable|string|max:50',
            'ai_bull_name' => 'nullable|string|max:100',
            'breeding_date' => 'date|before:tomorrow',
            'expected_delivery_date' => 'date|after:breeding_date',
            'status' => 'in:Pending,Confirmed Pregnant,Failed',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $breeding->update($request->only([
            'sire_id',
            'ai_semen_code',
            'ai_bull_name',
            'breeding_date',
            'expected_delivery_date',
            'status',
            'notes'
        ]));

        return response()->json([
            'status' => 'success',
            'message' => 'Breeding record updated',
            'data' => $breeding->load(['dam', 'sire'])
        ]);
    }

    // =================================================================
    // DESTROY: Delete breeding (only if no birth recorded)
    // =================================================================
    public function destroy(Request $request, $breeding_id)
    {
        $breeding = BreedingRecord::whereHas('dam.farmer', fn($q) => $q->where('farmer_id', $request->user()->farmer->id))
            ->findOrFail($breeding_id);

        if ($breeding->birthRecord()->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot delete breeding with recorded birth'
            ], 400);
        }

        $breeding->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Breeding record removed'
        ]);
    }

    // =================================================================
    // SUMMARY: Dashboard stats for breeding
    // =================================================================
    public function summary(Request $request)
    {
        $farmer = $request->user()->farmer;

        $breedings = $farmer->livestock()->with('breedingAsDam')->get()->pluck('breedingAsDam')->flatten();

        return response()->json([
            'status' => 'success',
            'data' => [
                'total_breedings' => $breedings->count(),
                'pregnant_now' => $breedings->where('status', 'Confirmed Pregnant')->count(),
                'success_rate' => $breedings->count() > 0
                    ? round($breedings->where('was_successful', true)->count() / $breedings->count() * 100, 1)
                    : 0,
                'ai_vs_natural' => [
                    'AI' => $breedings->where('breeding_type', 'AI')->count(),
                    'Natural' => $breedings->where('breeding_type', 'Natural')->count(),
                ],
                'due_soon' => $breedings->where('days_to_delivery', '>', 0)->where('days_to_delivery', '<=', 14)->count(),
                'overdue' => $breedings->where('is_overdue', true)->count(),
                'avg_calving_interval_days' => $breedings->whereNotNull('calving_interval')->avg('calving_interval'),
                'top_sire' => Livestock::where('farmer_id', $farmer->id)
                    ->withCount(['breedingAsSire as successes' => fn($q) => $q->whereHas('birthRecord')])
                    ->orderByDesc('successes')
                    ->first(['animal_id', 'tag_number', 'name']),
            ]
        ]);
    }

    // =================================================================
    // DROPDOWNS: For breeding form
    // =================================================================
    public function dropdowns(Request $request)
    {
        $farmerLivestock = $request->user()->farmer->livestock();

        return response()->json([
            'status' => 'success',
            'data' => [
                'dams' => $farmerLivestock->clone()
                    ->female()
                    ->active()
                    ->select('animal_id as value', DB::raw("CONCAT(tag_number, ' - ', COALESCE(name, 'No Name')) as label"))
                    ->orderBy('tag_number')
                    ->get(),

                'sires' => $farmerLivestock->clone()
                    ->male()
                    ->active()
                    ->select('animal_id as value', DB::raw("CONCAT(tag_number, ' - ', COALESCE(name, 'No Name')) as label"))
                    ->orderBy('tag_number')
                    ->get(),

                'breeding_types' => [
                    ['value' => 'Natural', 'label' => 'Natural Service'],
                    ['value' => 'AI', 'label' => 'Artificial Insemination (AI)'],
                ],

                'statuses' => [
                    ['value' => 'Pending', 'label' => 'Pending Confirmation'],
                    ['value' => 'Confirmed Pregnant', 'label' => 'Pregnant'],
                    ['value' => 'Failed', 'label' => 'Failed / Re-absorbed'],
                ],
            ]
        ]);
    }

    // =================================================================
    // ALERTS: Upcoming & overdue breedings
    // =================================================================
    public function alerts(Request $request)
    {
        $dueSoon = BreedingRecord::dueSoon(14)
            ->whereHas('dam.farmer', fn($q) => $q->where('farmer_id', $request->user()->farmer->id))
            ->with(['dam', 'sire'])
            ->get();

        $overdue = BreedingRecord::overdue()
            ->whereHas('dam.farmer', fn($q) => $q->where('farmer_id', $request->user()->farmer->id))
            ->with(['dam', 'sire'])
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'due_soon' => $dueSoon,
                'overdue' => $overdue,
                'total_alerts' => $dueSoon->count() + $overdue->count(),
            ]
        ]);
    }
}
