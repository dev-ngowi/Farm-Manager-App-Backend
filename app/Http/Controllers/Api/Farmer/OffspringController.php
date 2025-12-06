<?php

namespace App\Http\Controllers\Api\Farmer;

use App\Http\Controllers\Controller;
use App\Models\Offspring;
use App\Models\Delivery; // Used for context in store method
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class OffspringController extends Controller
{
    /**
     * Common query scope to ensure the offspring belongs to the farmer.
     */
    private function farmerScope($query, $farmerId)
    {
        // Offspring belongs to a Delivery, which belongs to an Insemination, which belongs to a Dam (Livestock), which belongs to the Farmer.
        return $query->whereHas('delivery.insemination.dam.farmer', fn($q) => $q->where('farmer_id', $farmerId));
    }

    /**
     * Display a listing of the offspring records for the farmer.
     * This includes basic related data for a list view.
     */
    public function index(Request $request)
    {
        $farmerId = $request->user()->farmer->id;

        $offspring = Offspring::with([
                // Fetch the dam's tag number for context
                'delivery.insemination.dam:id,tag_number,name',
                // Check if already registered as livestock
                'livestock:id,tag_number,name'
            ])
            ->where(function ($query) use ($farmerId) {
                return $this->farmerScope($query, $farmerId);
            })
            ->latest('id')
            ->paginate(15);

        return response()->json(['status' => 'success', 'data' => $offspring]);
    }

    /**
     * Display the specified offspring record.
     * Includes all related details for a detail view.
     */
    public function show(Request $request, $offspring_id)
    {
        $offspring = Offspring::with([
                'delivery.insemination.dam.species',
                'delivery.insemination.sire',
                'livestock'
            ])
            ->where(function ($query) use ($request) {
                return $this->farmerScope($query, $request->user()->farmer->id);
            })
            ->findOrFail($offspring_id);

        return response()->json(['status' => 'success', 'data' => $offspring]);
    }

    /**
     * Store a newly created offspring record in storage.
     * This is useful if a calf was missed during the initial delivery registration.
     */
    public function store(Request $request)
    {
        $farmerId = $request->user()->farmer->id;

        $validator = Validator::make($request->all(), [
            'delivery_id' => [
                'required',
                'exists:deliveries,id',
                // Ensure the delivery belongs to an animal owned by the farmer
                Rule::exists('deliveries', 'id')->where(function ($query) use ($farmerId) {
                    $query->whereHas('insemination.dam.farmer', fn($q) => $q->where('farmer_id', $farmerId));
                }),
            ],
            'temporary_tag' => 'nullable|string|max:50',
            'gender' => 'required|in:Male,Female,Unknown',
            'birth_weight_kg' => 'required|numeric|min:0',
            'birth_condition' => 'required|in:Vigorous,Weak,Stillborn',
            'colostrum_intake' => 'required|in:Adequate,Partial,Insufficient,None',
            'navel_treated' => 'required|boolean',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $delivery = Delivery::find($request->delivery_id);

        $offspring = $delivery->offspring()->create($validator->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Offspring record created successfully',
            'data' => $offspring
        ], 201);
    }

    /**
     * Update the specified offspring record in storage.
     */
    public function update(Request $request, $offspring_id)
    {
        $offspring = Offspring::where(function ($query) use ($request) {
                return $this->farmerScope($query, $request->user()->farmer->id);
            })
            ->findOrFail($offspring_id);

        $validator = Validator::make($request->all(), [
            'temporary_tag' => 'nullable|string|max:50',
            'gender' => 'sometimes|in:Male,Female,Unknown',
            'birth_weight_kg' => 'sometimes|numeric|min:0',
            'birth_condition' => 'sometimes|in:Vigorous,Weak,Stillborn',
            'colostrum_intake' => 'sometimes|in:Adequate,Partial,Insufficient,None',
            'navel_treated' => 'sometimes|boolean',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $offspring->update($validator->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Offspring record updated successfully',
            'data' => $offspring
        ]);
    }

    /**
     * Remove the specified offspring record from storage.
     */
    public function destroy(Request $request, $offspring_id)
    {
        $offspring = Offspring::where(function ($query) use ($request) {
                return $this->farmerScope($query, $request->user()->farmer->id);
            })
            ->findOrFail($offspring_id);

        // Prevent deletion if already registered as primary Livestock
        if ($offspring->livestock_id) {
             return response()->json([
                'status' => 'error',
                'message' => 'Cannot delete offspring already registered as livestock.'
            ], 403);
        }

        $offspring->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Offspring record deleted successfully'
        ], 200);
    }

    /**
     * Register an Offspring record as a new Livestock animal. (Existing method, slightly refined)
     */
    public function register(Request $request, $offspring_id)
    {
        $offspring = Offspring::where(function ($query) use ($request) {
                return $this->farmerScope($query, $request->user()->farmer->id);
            })
            ->findOrFail($offspring_id);

        $validator = Validator::make($request->all(), [
            'tag_number' => 'required|string|max:50|unique:livestock,tag_number',
            'name' => 'nullable|string|max:100',
            'species_id' => 'required|exists:species,id',
            'breed_id' => 'required|exists:breeds,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        if ($offspring->livestock_id) {
            return response()->json(['status' => 'error', 'message' => 'Offspring already registered'], 400);
        }

        // Assuming registerAsLivestock is a method on the Offspring model
        $livestock = $offspring->registerAsLivestock(array_merge(
            $validator->validated(),
            ['farmer_id' => $request->user()->farmer->id]
        ));

        return response()->json([
            'status' => 'success',
            'message' => 'Offspring registered as livestock',
            'data' => $livestock
        ], 201);
    }
}
