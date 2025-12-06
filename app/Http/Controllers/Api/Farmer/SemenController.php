<?php

namespace App\Http\Controllers\Api\Farmer;

use App\Http\Controllers\Controller;
use App\Models\Semen;
use App\Models\Livestock;
use App\Models\Breed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB; // Added for transaction
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule; // Added for unique rule in update

class SemenController extends Controller
{
    /**
     * Get data required for semen creation dropdowns (Owned Bulls and All Breeds).
     * Maps to the route: /api/v1/breeding/semen/dropdowns
     */
    public function dropdowns(Request $request)
    {
        $farmerId = $request->user()->farmer->id;
        Log::debug("SemenController@dropdowns: Starting request for farmer ID: $farmerId");

        // 1. Fetch Owned Bulls (Male livestock of species commonly used for semen)
        try {
            Log::debug("SemenController@dropdowns: Fetching owned bulls.");

            // ✅ FIX 1: Filter by 'sex' and corrected column name in whereHas
            // Assuming bulls are defined as Livestock with sex='Male' and species_name='Cattle'
            $ownedBulls = Livestock::ownedByFarmer($farmerId)
                ->where('sex', 'Male')
                // 🛑 CORRECTION: Replaced the problematic 'type' column check.
                // Using 'species_name' is a common column name, adjust if your column is different (e.g., 'name', 'category').
                ->whereHas('species', fn($q) => $q->where('species_name', 'Cattle'))
                ->select('animal_id', 'tag_number', 'name')
                ->get()
                ->map(fn($bull) => [
                    'value' => $bull->animal_id,
                    'label' => $bull->name . ' (' . $bull->tag_number . ')',
                    'type' => 'bull',
                ])
                ->toArray();
            Log::debug("SemenController@dropdowns: Fetched " . count($ownedBulls) . " bulls.");
        } catch (\Exception $e) {
            Log::error("SemenController@dropdowns: Error fetching bulls: " . $e->getMessage());
            // Return a 500 error response with the exception message for easier debugging
            return response()->json([
                'status' => 'error',
                'message' => 'Database query error in bull fetch: ' . $e->getMessage()
            ], 500);
        }

        // 2. Fetch All Breeds (Fixed array key)
        try {
            Log::debug("SemenController@dropdowns: Fetching all breeds.");
            $breeds = Breed::select('id', 'breed_name')
                ->get()
                ->map(fn($breed) => [
                    'value' => $breed->id,
                    'label' => $breed->breed_name,
                    'type' => 'breed', // ✅ FIX 2: Corrected key from '' to 'type'
                ])
                ->toArray();
            Log::debug("SemenController@dropdowns: Fetched " . count($breeds) . " breeds.");
        } catch (\Exception $e) {
            Log::error("SemenController@dropdowns: Error fetching breeds: " . $e->getMessage());
             return response()->json([
                'status' => 'error',
                'message' => 'Database query error in breed fetch: ' . $e->getMessage()
            ], 500);
        }

        // 3. Combine and return data
        $combinedDropdowns = array_merge($ownedBulls, $breeds);
        Log::debug("SemenController@dropdowns: Returning combined list of " . count($combinedDropdowns) . " items.");

        return response()->json([
            'status' => 'success',
            'data' => $combinedDropdowns,
        ]);
    }

    /**
     * Display a listing of semen inventory.
     */
    public function index(Request $request)
    {
        $farmerId = $request->user()->farmer->id;

        // Note: This relies on the Semen model having a scopeOwnedByFarmer($farmerId)
        $query = Semen::ownedByFarmer($farmerId)
            ->with([
                // Select only necessary bull fields for the list view
                'bull' => fn($q) => $q->select('animal_id', 'tag_number', 'name'),
                'breed' => fn($q) => $q->select('id', 'breed_name')
            ])
            ->select('id', 'straw_code', 'bull_id', 'bull_name', 'breed_id', 'collection_date', 'used', 'cost_per_straw');

        if ($request->boolean('available_only')) {
            $query->available();
        }

        if ($request->filled('breed_id')) {
            $query->where('breed_id', $request->breed_id);
        }

        $semen = $query->latest()->get();

        return response()->json([
            'status' => 'success',
            'data' => $semen,
            'meta' => [
                'total' => $semen->count(),
                // Using collection methods for metadata calculation
                'available' => $semen->where('used', 0)->count(),
                'used' => $semen->where('used', 1)->count(),
            ]
        ]);
    }

    /**
     * Get only available (unused) semen straws - for dropdowns
     */
    public function available(Request $request)
    {
        $farmerId = $request->user()->farmer->id;

        $semen = Semen::available()
            ->ownedByFarmer($farmerId) // Use the centralized ownership scope
            ->with(['bull' => fn($q) => $q->select('animal_id', 'tag_number'), 'breed'])
            ->select('id', 'straw_code', 'bull_name', 'breed_id')
            ->get()
            ->map(fn($item) => [
                'value' => $item->id,
                'label' => $item->straw_code . ' - ' . $item->bull_name . ' (' . ($item->breed->breed_name ?? 'Unknown') . ')'
            ]);

        return response()->json([
            'status' => 'success',
            'data' => $semen
        ]);
    }

    /**
     * Store a newly acquired semen straw.
     */
    public function store(Request $request)
    {
        $farmerId = $request->user()->farmer->id;

        $validator = Validator::make($request->all(), [
            'straw_code' => ['required', 'string', 'max:50', Rule::unique('semen_inventory', 'straw_code')],
            'bull_id' => 'nullable|exists:livestock,animal_id',
            'bull_tag' => 'nullable|string|max:50',
            'bull_name' => 'required|string|max:100',
            'breed_id' => 'required|exists:breeds,id',
            'collection_date' => 'required|date|before_or_equal:today',
            'dose_ml' => 'nullable|numeric|min:0.1|max:10',
            'motility_percentage' => 'nullable|integer|min:0|max:100',
            'cost_per_straw' => 'nullable|numeric|min:0',
            'source_supplier' => 'nullable|string|max:150',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        // --- SECURITY CHECK & TRANSACTION ---
        try {
            DB::beginTransaction();

            // Ensure bull belongs to farmer if bull_id is provided
            if ($request->bull_id) {
                Livestock::where('animal_id', $request->bull_id)
                    ->where('farmer_id', $farmerId)
                    ->firstOrFail();
            }

            // --- PRIORITY FIX: ASSIGN farmer_id ---
            $semen = Semen::create([
                'farmer_id' => $farmerId, // <-- Crucial for ownership checks
                'straw_code' => $request->straw_code,
                'bull_id' => $request->bull_id,
                'bull_tag' => $request->bull_tag,
                'bull_name' => $request->bull_name,
                'breed_id' => $request->breed_id,
                'collection_date' => $request->collection_date,
                // Ensure dose_ml and cost_per_straw are treated as numeric defaults
                'dose_ml' => $request->dose_ml ?? 0.25,
                'motility_percentage' => $request->motility_percentage,
                'cost_per_straw' => $request->cost_per_straw ?? 0,
                'source_supplier' => $request->source_supplier,
                'used' => false,
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Semen straw added to inventory',
                'data' => $semen->load(['bull', 'breed'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            // Handle specific exceptions or return a generic server error
            return response()->json([
                'status' => 'error',
                'message' => 'Could not save semen record: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified semen straw.
     */
    public function show(Request $request, $id)
    {
        $farmerId = $request->user()->farmer->id;

        $semen = Semen::ownedByFarmer($farmerId) // Use ownership scope
            ->with([
                'bull' => fn($q) => $q->select('animal_id', 'tag_number', 'name', 'species_id'),
                'breed',
                // Load related inseminations for the usage history
                'inseminations' => fn($q) => $q->with([
                        // Select only necessary dam fields
                        'dam' => fn($sq) => $sq->select('animal_id', 'tag_number', 'name')
                    ])
                    ->select('id', 'dam_id', 'insemination_date', 'status')
            ])
            ->findOrFail($id);

        // Assumes success_rate is an accessor on the Semen model
        $stats = [
            'times_used' => $semen->inseminations->count(),
            // Assumes success_rate accessor exists, otherwise needs manual calculation
            'success_rate' => property_exists($semen, 'success_rate')
                ? $semen->success_rate . '%'
                : 'N/A',
            'conceptions' => $semen->inseminations->where('status', 'Confirmed Pregnant')->count(),
        ];

        return response()->json([
            'status' => 'success',
            'data' => $semen,
            'stats' => $stats
        ]);
    }

    /**
     * Update the specified semen straw.
     */
    public function update(Request $request, $id)
    {
        $farmerId = $request->user()->farmer->id;

        // Fetch and authorize the semen record first
        $semen = Semen::ownedByFarmer($farmerId)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            // Use Rule::unique to ignore the current record's ID
            'straw_code' => ['sometimes', 'string', 'max:50', Rule::unique('semen_inventory', 'straw_code')->ignore($id)],
            'bull_id' => 'nullable|exists:livestock,animal_id',
            'bull_tag' => 'nullable|string|max:50',
            // Corrected: Removed 'required' when using 'sometimes'
            'bull_name' => 'sometimes|string|max:100',
            'breed_id' => 'sometimes|exists:breeds,id',
            'collection_date' => 'sometimes|date|before_or_equal:today',
            'cost_per_straw' => 'nullable|numeric|min:0',
            'source_supplier' => 'nullable|string|max:150',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check ownership of new bull_id if it's being updated
        if ($request->filled('bull_id')) {
            Livestock::where('animal_id', $request->bull_id)
                ->where('farmer_id', $farmerId)
                ->firstOrFail();
        }

        $semen->update($request->only([
            'straw_code', 'bull_id', 'bull_tag', 'bull_name',
            'breed_id', 'collection_date', 'cost_per_straw', 'source_supplier'
        ]));

        return response()->json([
            'status' => 'success',
            'message' => 'Semen record updated',
            'data' => $semen->load(['bull', 'breed'])
        ]);
    }

    /**
     * Remove the specified semen straw from inventory.
     */
    public function destroy(Request $request, $id)
    {
        $farmerId = $request->user()->farmer->id;

        // Fetch and authorize the semen record first
        $semen = Semen::ownedByFarmer($farmerId)->findOrFail($id);

        if ($semen->inseminations()->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot delete semen record that has been used in inseminations.'
            ], 400);
        }

        $semen->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Semen straw removed from inventory'
        ]);
    }
}
