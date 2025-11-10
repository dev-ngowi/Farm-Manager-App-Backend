<?php

namespace App\Http\Controllers\Api\Farmer;

use App\Http\Controllers\Controller;
use App\Models\Livestock;
use App\Models\Species;
use App\Models\Breed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class LivestockController extends Controller
{
    // =================================================================
    // INDEX: Get all animals for logged-in farmer
    // =================================================================
    public function index(Request $request)
    {
        $query = $request->user()->farmer->livestock()
            ->with([
                'species' => fn($q) => $q->select('id', 'species_name'),
                'breed' => fn($q) => $q->select('id', 'breed_name', 'purpose'),
                'sire' => fn($q) => $q->select('animal_id', 'tag_number', 'name'),
                'dam' => fn($q) => $q->select('animal_id', 'tag_number', 'name'),
            ])
            ->withCount(['milkYields', 'offspringAsDam', 'offspringAsSire']);

        // Filters
        if ($request->filled('species_id')) {
            $query->where('species_id', $request->species_id);
        }
        if ($request->filled('breed_id')) {
            $query->where('breed_id', $request->breed_id);
        }
        if ($request->filled('sex')) {
            $query->where('sex', $request->sex);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->boolean('pregnant')) {
            $query->pregnant();
        }
        if ($request->boolean('milking')) {
            $query->milking();
        }
        if ($request->boolean('market_ready')) {
            $query->marketReady();
        }

        $livestock = $query->orderBy('tag_number')->paginate(20);

        return response()->json([
            'status' => 'success',
            'data' => $livestock
        ], 200);
    }

    // =================================================================
    // SHOW: Single animal details
    // =================================================================
    public function show(Request $request, $animal_id)
    {
        $animal = $request->user()->farmer->livestock()
            ->with([
                'species' => fn($q) => $q->select('id', 'species_name'),
                'breed' => fn($q) => $q->select('id', 'breed_name', 'purpose'),
                'sire' => fn($q) => $q->select('animal_id', 'tag_number', 'name'),
                'dam' => fn($q) => $q->select('animal_id', 'tag_number', 'name'),
                'offspringAsDam' => fn($q) => $q->take(5),
                'offspringAsSire' => fn($q) => $q->take(5),
                'milkYields' => fn($q) => $q->where('yield_date', '>=', now()->subDays(30))->orderByDesc('yield_date'),
                'weightRecords' => fn($q) => $q->latest('record_date')->take(10),
                'vetAppointments' => fn($q) => $q->upcoming()->take(3),
            ])
            ->withCount(['income', 'expenses'])
            ->findOrFail($animal_id);

        return response()->json([
            'status' => 'success',
            'data' => $animal
        ], 200);
    }

    // =================================================================
    // STORE: Add new animal
    // =================================================================
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'species_id' => 'required|exists:species,id',
            'breed_id' => 'required|exists:breeds,id',
            'tag_number' => 'required|string|max:50|unique:livestock,tag_number',
            'name' => 'nullable|string|max:100',
            'sex' => 'required|in:Male,Female',
            'date_of_birth' => 'required|date|before:today',
            'weight_at_birth_kg' => 'required|numeric|min:0',
            'sire_id' => 'nullable|exists:livestock,animal_id',
            'dam_id' => 'nullable|exists:livestock,animal_id',
            'purchase_date' => 'nullable|date',
            'purchase_cost' => 'nullable|numeric|min:0',
            'source' => 'nullable|string|max:100',
            'status' => 'required|in:Active,Sold,Dead,Stolen',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $animal = $request->user()->farmer->livestock()->create($request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'Animal registered successfully',
            'data' => $animal->load('species', 'breed')
        ], 201);
    }

    // =================================================================
    // UPDATE: Edit animal
    // =================================================================
    public function update(Request $request, $animal_id)
    {
        $animal = $request->user()->farmer->livestock()->findOrFail($animal_id);

        $validator = Validator::make($request->all(), [
            'tag_number' => 'string|max:50|unique:livestock,tag_number,' . $animal_id . ',animal_id',
            'name' => 'nullable|string|max:100',
            'current_weight_kg' => 'nullable|numeric|min:0',
            'status' => 'in:Active,Sold,Dead,Stolen',
            'disposal_date' => 'nullable|date',
            'disposal_reason' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $animal->update($request->only([
            'tag_number',
            'name',
            'current_weight_kg',
            'status',
            'disposal_date',
            'disposal_reason',
            'notes'
        ]));

        return response()->json([
            'status' => 'success',
            'message' => 'Animal updated successfully',
            'data' => $animal->load('species', 'breed')
        ], 200);
    }

    // =================================================================
    // DESTROY: Delete animal (with safety checks)
    // =================================================================
    public function destroy(Request $request, $animal_id)
    {
        $animal = $request->user()->farmer->livestock()->findOrFail($animal_id);

        if ($animal->milkYields()->exists() ||
            $animal->offspringAsDam()->exists() ||
            $animal->offspringAsSire()->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot delete animal with milk records or offspring'
            ], 400);
        }

        $animal->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Animal removed successfully'
        ], 200);
    }

    // =================================================================
    // DASHBOARD SUMMARY
    // =================================================================
    public function summary(Request $request)
    {
        $farmer = $request->user()->farmer;

        $todayMilkSubquery = DB::table('milk_yield_records')
            ->whereColumn('animal_id', 'livestock.animal_id')
            ->whereDate('yield_date', today())
            ->selectRaw('COALESCE(SUM(quantity_liters), 0)')
            ->limit(1);

        return response()->json([
            'status' => 'success',
            'data' => [
                'total_animals' => $farmer->livestock()->count(),
                'active_females' => $farmer->livestock()->female()->active()->count(),
                'milking_cows' => $farmer->livestock()->milking()->count(),
                'pregnant' => $farmer->livestock()->pregnant()->count(),
                'due_soon' => $farmer->livestock()->dueSoon(14)->count(),
                'market_ready' => $farmer->livestock()->marketReady()->count(),
                'total_milk_today' => $farmer->livestock()->milking()
                    ->selectRaw("COALESCE(({$todayMilkSubquery->toSql()}), 0) as today_milk")
                    ->mergeBindings($todayMilkSubquery)
                    ->sum('today_milk'),
                'top_earner' => $farmer->livestock()
                    ->withSum('income', 'amount')
                    ->orderByDesc('income_sum_amount')
                    ->first(['animal_id', 'tag_number', 'name']),
            ]
        ], 200);
    }

    // =================================================================
    // DROPDOWNS: For forms (species, breeds, parents)
    // =================================================================
    public function dropdowns(Request $request)
    {
        return response()->json([
            'status' => 'success',
            'data' => [
                'species' => Species::select('id as value', 'species_name as label')
                    ->orderBy('species_name')
                    ->get(),

                'breeds' => Breed::select('id as value', 'breed_name as label', 'species_id')
                    ->with('species:id,species_name')
                    ->orderBy('breed_name')
                    ->get(),

                'parents' => $request->user()->farmer->livestock()
                    ->select(
                        'animal_id as value',
                        DB::raw("CONCAT(tag_number, ' - ', COALESCE(name, 'No Name')) as label"),
                        'sex'
                    )
                    ->whereIn('sex', ['Male', 'Female'])
                    ->active()
                    ->orderBy('tag_number')
                    ->get(),
            ]
        ], 200);
    }
}
