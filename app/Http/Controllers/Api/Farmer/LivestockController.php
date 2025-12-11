<?php

namespace App\Http\Controllers\Api\Farmer;

use App\Http\Controllers\Controller;
use App\Models\Livestock;
use App\Models\Species;
use App\Models\Breed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

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
        // Ensure the farmer object exists before trying to access its ID
        $farmer = $request->user()->farmer;

        if (!$farmer) {
            return response()->json([
                'status' => 'error',
                'message' => 'User is not registered as a farmer.'
            ], 403);
        }

        // 1. LOG: Log the incoming request data before validation
        Log::info('Incoming Livestock Data:', $request->all());

        $validator = Validator::make($request->all(), [
            'species_id' => 'required|exists:species,id',
            'breed_id' => 'required|exists:breeds,id',

            // ⭐ THE FIX: Use Rule::unique to enforce uniqueness scoped to the farmer_id
            'tag_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique('livestock', 'tag_number')->where(function ($query) use ($farmer) {
                    return $query->where('farmer_id', $farmer->id);
                }),
            ],

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
            // 2. LOG: Log the validation errors when validation fails
            Log::error('Livestock Validation Failed:', $validator->errors()->toArray());

            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        // 3. LOG: Log successful validation before creation
        Log::info('Livestock Validation Successful. Attempting creation.');

        // Prepare data, ensuring farmer_id is automatically added
        $data = array_merge($request->all(), [
            'farmer_id' => $farmer->id,
        ]);

        // This line correctly relates the animal to the authenticated farmer
        // Alternatively, you can use the relationship method:
        // $animal = $farmer->livestock()->create($request->all()); // This assumes farmer_id is NOT in $request->all()

        // Since your existing code uses the relationship:
        $animal = $farmer->livestock()->create($request->all());
        // (This line is correct because Laravel automatically sets the foreign key 'farmer_id')

        // 4. LOG: Log successful creation
        Log::info('Livestock created successfully:', ['animal_id' => $animal->animal_id]);

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
        $farmer = $request->user()->farmer;

        // 1. Authorization & Fetch
        $animal = $farmer->livestock()->find($animal_id);

        if (!$animal) {
            return response()->json([
                'status' => 'error',
                'message' => 'Animal not found or does not belong to this farmer.'
            ], 404);
        }

        // 2. Validation
        $validator = Validator::make($request->all(), [
            'species_id' => 'required|exists:species,id',
            'breed_id' => [
                'nullable',
                // FIX: Check if breed_id is valid for the species_id provided in the request
                Rule::exists('breeds', 'id')->where(function ($query) use ($request) {
                     return $query->where('species_id', $request->input('species_id'));
                }),
            ],
            'tag_number' => [
                'required',
                'string',
                'max:255',
                // Unique rule, excluding the current animal's tag number
                Rule::unique('livestock', 'tag_number')->ignore($animal->animal_id, 'animal_id')->where('farmer_id', $farmer->id),
            ],
            'name' => 'nullable|string|max:255',
            'sex' => 'required|in:Male,Female,Unknown',
            'date_of_birth' => 'required|date',
            'weight_at_birth_kg' => 'required|numeric|min:0',
            'status' => 'required|in:Active,Sold,Dead,Stolen',
            'notes' => 'nullable|string',
            // Optional purchase details
            'purchase_date' => 'nullable|date',
            'purchase_cost' => 'nullable|numeric|min:0',
            'source' => 'nullable|string|max:255',
            // Parent IDs must exist and belong to the same farmer
            'sire_id' => 'nullable|exists:livestock,animal_id',
            'dam_id' => 'nullable|exists:livestock,animal_id',
        ]);

        if ($validator->fails()) {
            Log::error('Livestock Update Validation Failed:', $validator->errors()->toArray());
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }
        
        // ⭐ THE FIX: Get the validated data array from the Validator instance
        $validatedData = $validator->validated();

        // 3. Update & Save
        try {
            DB::beginTransaction();
            // ⭐ Use the validated data array
            $animal->update($validatedData); 
            DB::commit();

            // 4. Return the updated animal (eager-loaded as in show method)
            $animal->load([
                'species' => fn($q) => $q->select('id', 'species_name'),
                'breed' => fn($q) => $q->select('id', 'breed_name', 'purpose'),
                'sire' => fn($q) => $q->select('animal_id', 'tag_number', 'name'),
                'dam' => fn($q) => $q->select('animal_id', 'tag_number', 'name'),
                'offspringAsDam' => fn($q) => $q->take(5),
                'offspringAsSire' => fn($q) => $q->take(5),
            ])
            ->loadCount(['milkYields', 'offspringAsDam', 'offspringAsSire', 'income', 'expenses']);

            return response()->json([
                'status' => 'success',
                'message' => 'Livestock details updated successfully.',
                'data' => $animal
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Livestock Update Failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update livestock details.'
            ], 500);
        }
    }

    // =================================================================
    // DESTROY: Delete an animal
    // =================================================================
    public function destroy(Request $request, $animal_id)
    {
        $farmer = $request->user()->farmer;

        // 1. Authorization & Fetch
        $animal = $farmer->livestock()->find($animal_id);

        if (!$animal) {
            return response()->json([
                'status' => 'error',
                'message' => 'Animal not found or does not belong to this farmer.'
            ], 404);
        }

        // 2. Delete (Can also check for related records before deleting)
        try {
            $animal->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Livestock deleted successfully.'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Livestock Delete Failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete livestock.'
            ], 500);
        }
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
