<?php

namespace App\Http\Controllers\Api\Farmer;

use App\Http\Controllers\Controller;
use App\Models\BirthRecord;
use App\Models\BreedingRecord;
use App\Models\Livestock;
use App\Models\OffspringRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BirthRecordController extends Controller
{
    // =================================================================
    // INDEX: List all birth records
    // =================================================================
    public function index(Request $request)
    {
        $query = BirthRecord::whereHas('breeding.dam.farmer', fn($q) => $q->where('farmer_id', $request->user()->farmer->id))
            ->with([
                'breeding.dam' => fn($q) => $q->select('animal_id', 'tag_number', 'name'),
                'breeding.sire' => fn($q) => $q->select('animal_id', 'tag_number', 'name'),
                'vet' => fn($q) => $q->select('id', 'name', 'phone'),
                'offspringRecords.offspring' => fn($q) => $q->select('animal_id', 'tag_number', 'sex', 'name')
            ])
            ->withCount(['liveOffspring as live_count', 'offspringRecords as total_count'])
            ->orderByDesc('birth_date');

        // Filters
        if ($request->boolean('this_month')) $query->thisMonth();
        if ($request->boolean('this_year')) $query->thisYear();
        if ($request->boolean('complications')) $query->withComplications();
        if ($request->boolean('twins')) $query->twinsOrMore();
        if ($request->boolean('stillbirths')) $query->stillbirths();
        if ($request->boolean('assisted')) $query->assistedOrCsection();
        if ($request->filled('vet_id')) $query->byVet($request->vet_id);

        $births = $query->paginate(20);

        return response()->json([
            'status' => 'success',
            'data' => $births
        ]);
    }

    // =================================================================
    // SHOW: Single birth record
    // =================================================================
    public function show(Request $request, $birth_id)
    {
        $birth = BirthRecord::whereHas('breeding.dam.farmer', fn($q) => $q->where('farmer_id', $request->user()->farmer->id))
            ->with([
                'breeding.dam',
                'breeding.sire',
                'vet',
                'offspringRecords.offspring',
                'pregnancyChecks' => fn($q) => $q->orderByDesc('check_date'),
                'expenses',
                'incomeFromOffspring'
            ])
            ->findOrFail($birth_id);

        return response()->json([
            'status' => 'success',
            'data' => $birth
        ]);
    }

    // =================================================================
    // STORE: Record new birth + auto-create offspring
    // =================================================================
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'breeding_id' => 'required|exists:breeding_records,breeding_id',
            'birth_date' => 'required|date|before:tomorrow',
            'birth_time' => 'required|date_format:H:i',
            'total_offspring' => 'required|integer|min:1|max:10',
            'live_births' => 'required|integer|min:0|lte:total_offspring',
            'stillbirths' => 'required|integer|min:0',
            'birth_type' => 'required|in:Natural,Assisted,Cesarean',
            'complications' => 'nullable|string|max:1000',
            'dam_condition' => 'required|in:Excellent,Good,Fair,Poor,Critical',
            'vet_id' => 'nullable|exists:veterinarians,id',
            'notes' => 'nullable|string',

            // Offspring details
            'offspring' => 'required|array|size:' . $request->input('total_offspring'),
            'offspring.*.sex' => 'required|in:Male,Female',
            'offspring.*.weight_at_birth_kg' => 'required|numeric|min:0.1|max:100',
            'offspring.*.health_status' => 'required|in:Healthy,Weak,Deceased',
            'offspring.*.tag_number' => 'required|string|max:50|unique:livestock,tag_number',
            'offspring.*.name' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Security: breeding must belong to farmer
        $breeding = BreedingRecord::where('breeding_id', $request->breeding_id)
            ->whereHas('dam.farmer', fn($q) => $q->where('farmer_id', $request->user()->farmer->id))
            ->firstOrFail();

        // Prevent duplicate birth record
        if ($breeding->birthRecord()->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Birth already recorded for this breeding'
            ], 400);
        }

        DB::transaction(function () use ($request, $breeding) {
            // 1. Create Birth Record
            $birth = BirthRecord::create([
                'breeding_id' => $request->breeding_id,
                'birth_date' => $request->birth_date,
                'birth_time' => $request->birth_time,
                'total_offspring' => $request->total_offspring,
                'live_births' => $request->live_births,
                'stillbirths' => $request->stillbirths,
                'birth_type' => $request->birth_type,
                'complications' => $request->complications,
                'dam_condition' => $request->dam_condition,
                'vet_id' => $request->vet_id,
                'notes' => $request->notes,
            ]);

            // 2. Create Offspring + Livestock
            foreach ($request->offspring as $index => $calf) {
                $livestock = Livestock::create([
                    'farmer_id' => $breeding->dam->farmer_id,
                    'species_id' => $breeding->dam->species_id,
                    'breed_id' => $breeding->dam->breed_id,
                    'tag_number' => $calf['tag_number'],
                    'name' => $calf['name'] ?? null,
                    'sex' => $calf['sex'],
                    'date_of_birth' => $request->birth_date,
                    'weight_at_birth_kg' => $calf['weight_at_birth_kg'],
                    'dam_id' => $breeding->dam_id,
                    'sire_id' => $breeding->sire_id,
                    'status' => 'Active',
                    'source' => 'Born on Farm',
                ]);

                OffspringRecord::create([
                    'birth_id' => $birth->birth_id,
                    'livestock_id' => $livestock->animal_id,
                    'birth_order' => $index + 1,
                    'health_status' => $calf['health_status'],
                    'weight_at_birth_kg' => $calf['weight_at_birth_kg'],
                    'notes' => $calf['name'] ?? "Calf " . ($index + 1),
                ]);
            }

            // 3. Update breeding status
            $breeding->update(['status' => 'Delivered']);
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Birth recorded and ' . $request->live_births . ' calves registered successfully',
            'data' => BirthRecord::with(['offspringRecords.offspring'])->find($birth->birth_id ?? DB::getPdo()->lastInsertId())
        ], 201);
    }

    // =================================================================
    // UPDATE: Edit birth record
    // =================================================================
    public function update(Request $request, $birth_id)
    {
        $birth = BirthRecord::whereHas('breeding.dam.farmer', fn($q) => $q->where('farmer_id', $request->user()->farmer->id))
            ->findOrFail($birth_id);

        $validator = Validator::make($request->all(), [
            'birth_time' => 'date_format:H:i',
            'birth_type' => 'in:Natural,Assisted,Cesarean',
            'complications' => 'nullable|string|max:1000',
            'dam_condition' => 'in:Excellent,Good,Fair,Poor,Critical',
            'vet_id' => 'nullable|exists:veterinarians,id',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $birth->update($request->only([
            'birth_time', 'birth_type', 'complications',
            'dam_condition', 'vet_id', 'notes'
        ]));

        return response()->json([
            'status' => 'success',
            'message' => 'Birth record updated',
            'data' => $birth->load(['breeding.dam', 'offspringRecords.offspring'])
        ]);
    }

    // =================================================================
    // DESTROY: Delete birth (only if no sales/income)
    // =================================================================
    public function destroy(Request $request, $birth_id)
    {
        $birth = BirthRecord::whereHas('breeding.dam.farmer', fn($q) => $q->where('farmer_id', $request->user()->farmer->id))
            ->findOrFail($birth_id);

        if ($birth->offspring()->whereHas('income')->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot delete birth with sold offspring'
            ], 400);
        }

        DB::transaction(function () use ($birth) {
            $birth->offspringRecords()->delete();
            $birth->offspring()->delete();
            $birth->delete();
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Birth record and offspring removed'
        ]);
    }

    // =================================================================
    // SUMMARY: Dashboard stats
    // =================================================================
    public function summary(Request $request)
    {
        $farmerId = $request->user()->farmer->id;
        $thisYear = now()->year;
        $thisMonth = now()->format('Y-m');

        $births = BirthRecord::whereHas('breeding.dam.farmer', fn($q) => $q->where('farmer_id', $farmerId))
            ->thisYear()
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'total_births' => $births->count(),
                'total_calves' => $births->sum('total_offspring'),
                'live_calves' => $births->sum('live_births'),
                'success_rate' => $births->avg('success_rate'),
                'twins_rate' => $births->where('had_twins', true)->count() / max($births->count(), 1) * 100,
                'assisted_rate' => $births->where('was_assisted', true)->count() / max($births->count(), 1) * 100,
                'this_month' => BirthRecord::whereHas('breeding.dam.farmer', fn($q) => $q->where('farmer_id', $farmerId))
                    ->whereRaw("DATE_FORMAT(birth_date, '%Y-%m') = ?", [$thisMonth])
                    ->count(),
                'avg_calving_interval' => $births->avg('calving_interval'),
                'on_time_delivery' => $births->where('was_on_time', true)->count(),
            ]
        ]);
    }

    // =================================================================
    // DROPDOWNS: For birth form
    // =================================================================
    public function dropdowns(Request $request)
    {
        return response()->json([
            'status' => 'success',
            'data' => [
                'birth_types' => [
                    ['value' => 'Natural', 'label' => 'Natural (Unassisted)'],
                    ['value' => 'Assisted', 'label' => 'Assisted (Pulling)'],
                    ['value' => 'Cesarean', 'label' => 'Cesarean Section'],
                ],
                'dam_conditions' => [
                    ['value' => 'Excellent', 'label' => 'Excellent'],
                    ['value' => 'Good', 'label' => 'Good'],
                    ['value' => 'Fair', 'label' => 'Fair'],
                    ['value' => 'Poor', 'label' => 'Poor'],
                    ['value' => 'Critical', 'label' => 'Critical'],
                ],
                'health_statuses' => [
                    ['value' => 'Healthy', 'label' => 'Healthy'],
                    ['value' => 'Weak', 'label' => 'Weak'],
                    ['value' => 'Deceased', 'label' => 'Deceased'],
                ],
                'vets' => \App\Models\Veterinarian::select('id as value', 'name as label', 'phone')
                    ->orderBy('name')
                    ->get(),
            ]
        ]);
    }

    // =================================================================
    // ALERTS: Recent births & complications
    // =================================================================
    public function alerts(Request $request)
    {
        $recent = BirthRecord::whereHas('breeding.dam.farmer', fn($q) => $q->where('farmer_id', $request->user()->farmer->id))
            ->where('birth_date', '>=', now()->subDays(7))
            ->with(['breeding.dam', 'vet'])
            ->get();

        $complications = BirthRecord::withComplications()
            ->whereHas('breeding.dam.farmer', fn($q) => $q->where('farmer_id', $request->user()->farmer->id))
            ->where('birth_date', '>=', now()->subDays(30))
            ->with(['breeding.dam'])
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'recent_births' => $recent,
                'needs_attention' => $complications,
                'total' => $recent->count() + $complications->count(),
            ]
        ]);
    }
}
