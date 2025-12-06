<?php

namespace App\Http\Controllers\Api\Farmer;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\HeatCycle;
use App\Models\Offspring;
use App\Models\Insemination;
use App\Models\Lactation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DeliveryController extends Controller
{
    /**
     * Display a listing of the resource (Deliveries).
     */
    public function index(Request $request)
    {
        $farmerId = $request->user()->farmer->id;

        $query = Delivery::query()
            // Ensure all deliveries belong to the current farmer
            ->whereHas('insemination.dam.farmer', fn($q) => $q->where('farmer_id', $farmerId))
            ->with([
                // Load Dam details via Insemination
                'insemination' => fn($q) => $q->select('id', 'dam_id', 'insemination_date')
                    ->with(['dam:id,tag_number,name']),
                'offspring:id,delivery_id,gender,birth_condition',
            ]);

        // Optional: Add filtering if needed, e.g., by delivery_type
        if ($request->filled('delivery_type')) {
            $query->where('delivery_type', $request->delivery_type);
        }

        $deliveries = $query->latest('actual_delivery_date')->get();

        return response()->json([
            'status' => 'success',
            'data' => $deliveries,
            'meta' => ['total' => $deliveries->count()]
        ]);
    }

    /**
     * Store a newly created resource in storage (CREATE).
     * (Your original store method, wrapped in a transaction.)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'insemination_id' => 'required|exists:inseminations,id',
            'actual_delivery_date' => 'required|date|before_or_equal:today',
            'delivery_type' => 'required|in:Normal,Assisted,C-Section,Dystocia',
            'calving_ease_score' => 'required|integer|min:1|max:5',
            'dam_condition_after' => 'required|in:Excellent,Good,Weak,Critical',
            'offspring' => 'required|array|min:1',
            'offspring.*.gender' => 'required|in:Male,Female',
            'offspring.*.birth_weight_kg' => 'required|numeric|min:0',
            'offspring.*.birth_condition' => 'required|in:Vigorous,Weak,Stillborn',
            'offspring.*.colostrum_intake' => 'required|in:Adequate,Partial,Insufficient,None',
            'offspring.*.navel_treated' => 'boolean',
            'offspring.*.temporary_tag' => 'nullable|string|max:50',
            'offspring.*.notes' => 'nullable|string', // Ensure notes is handled here
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $insemination = Insemination::whereHas('dam.farmer', fn($q) => $q->where('farmer_id', $request->user()->farmer->id))
            ->findOrFail($request->insemination_id);

        if ($insemination->status !== 'Confirmed Pregnant') {
            return response()->json(['status' => 'error', 'message' => 'Insemination not confirmed pregnant'], 400);
        }

        return DB::transaction(function () use ($request, $insemination) {
            $offspringData = $request->offspring;
            $totalBorn = count($offspringData);
            $liveBorn = count(array_filter($offspringData, fn($o) => $o['birth_condition'] !== 'Stillborn'));
            $stillborn = $totalBorn - $liveBorn;

            $delivery = Delivery::create([
                'insemination_id' => $insemination->id,
                'actual_delivery_date' => $request->actual_delivery_date,
                'delivery_type' => $request->delivery_type,
                'calving_ease_score' => $request->calving_ease_score,
                'total_born' => $totalBorn,
                'live_born' => $liveBorn,
                'stillborn' => $stillborn,
                'dam_condition_after' => $request->dam_condition_after,
                'notes' => $request->notes,
            ]);

            foreach ($offspringData as $offspring) {
                Offspring::create([
                    'delivery_id' => $delivery->id,
                    'temporary_tag' => $offspring['temporary_tag'] ?? null,
                    'gender' => $offspring['gender'],
                    'birth_weight_kg' => $offspring['birth_weight_kg'],
                    'birth_condition' => $offspring['birth_condition'],
                    'colostrum_intake' => $offspring['colostrum_intake'],
                    'navel_treated' => $offspring['navel_treated'] ?? false,
                    'notes' => $offspring['notes'] ?? null,
                ]);
            }

            // Update related records
            $insemination->update(['status' => 'Delivered']);
            $dam = $insemination->dam;

            // Start lactation period
            $lactationNumber = $dam->lactations()->count() + 1;
            Lactation::create([
                'dam_id' => $dam->animal_id,
                'lactation_number' => $lactationNumber,
                'start_date' => $delivery->actual_delivery_date,
                'status' => 'Ongoing',
            ]);

            // Schedule next heat cycle
            HeatCycle::create([
                'dam_id' => $dam->animal_id,
                'observed_date' => null,
                'next_expected_date' => Carbon::parse($delivery->actual_delivery_date)->addDays(80),
                'inseminated' => false,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Delivery and offspring recorded',
                'data' => $delivery->load(['offspring', 'insemination.dam'])
            ], 201);
        });
    }

    /**
     * Display the specified resource (READ).
     */
    public function show(Request $request, Delivery $delivery)
    {
        // Security check: Ensure the delivery belongs to the current farmer
        if ($delivery->insemination->dam->farmer_id !== $request->user()->farmer->id) {
            return response()->json(['status' => 'error', 'message' => 'Record not found.'], 404);
        }

        return response()->json([
            'status' => 'success',
            // Load necessary relationships for the detail view
            'data' => $delivery->load(['offspring', 'insemination.dam', 'insemination.breedingMethod'])
        ]);
    }

    /**
     * Update the specified resource in storage (UPDATE).
     */
    public function update(Request $request, Delivery $delivery)
    {
        // Security check
        if ($delivery->insemination->dam->farmer_id !== $request->user()->farmer->id) {
            return response()->json(['status' => 'error', 'message' => 'Record not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'actual_delivery_date' => 'sometimes|required|date|before_or_equal:today',
            'delivery_type' => 'sometimes|required|in:Normal,Assisted,C-Section,Dystocia',
            'calving_ease_score' => 'sometimes|required|integer|min:1|max:5',
            'dam_condition_after' => 'sometimes|required|in:Excellent,Good,Weak,Critical',
            'offspring' => 'sometimes|required|array|min:1',
            // Crucially, include offspring ID for existing records or null for new ones
            'offspring.*.id' => 'nullable|exists:offspring,id',
            'offspring.*.gender' => 'required|in:Male,Female',
            'offspring.*.birth_weight_kg' => 'required|numeric|min:0',
            'offspring.*.birth_condition' => 'required|in:Vigorous,Weak,Stillborn',
            'offspring.*.colostrum_intake' => 'required|in:Adequate,Partial,Insufficient,None',
            'offspring.*.navel_treated' => 'boolean',
            'offspring.*.temporary_tag' => 'nullable|string|max:50',
            'offspring.*.notes' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        return DB::transaction(function () use ($request, $delivery) {
            $offspringData = $request->offspring ?? [];

            // Calculate new totals
            $totalBorn = count($offspringData);
            $liveBorn = count(array_filter($offspringData, fn($o) => $o['birth_condition'] !== 'Stillborn'));
            $stillborn = $totalBorn - $liveBorn;

            // 1. Update the Delivery record
            $delivery->update($request->only([
                'actual_delivery_date', 'delivery_type', 'calving_ease_score', 'dam_condition_after', 'notes'
            ]) + compact('total_born', 'live_born', 'stillborn'));

            $existingOffspringIds = $delivery->offspring->pluck('id')->toArray();
            $updatedOffspringIds = [];

            // 2. Sync Offspring Records
            foreach ($offspringData as $offspring) {
                $id = $offspring['id'] ?? null;
                $data = [
                    'delivery_id' => $delivery->id,
                    'temporary_tag' => $offspring['temporary_tag'] ?? null,
                    'gender' => $offspring['gender'],
                    'birth_weight_kg' => $offspring['birth_weight_kg'],
                    'birth_condition' => $offspring['birth_condition'],
                    'colostrum_intake' => $offspring['colostrum_intake'],
                    'navel_treated' => $offspring['navel_treated'] ?? false,
                    'notes' => $offspring['notes'] ?? null,
                ];

                if ($id && in_array($id, $existingOffspringIds)) {
                    // Update existing offspring
                    Offspring::find($id)->update($data);
                    $updatedOffspringIds[] = $id;
                } else {
                    // Create new offspring
                    $newOffspring = Offspring::create($data);
                    $updatedOffspringIds[] = $newOffspring->id;
                }
            }

            // 3. Delete offspring that were removed from the request
            $deletedIds = array_diff($existingOffspringIds, $updatedOffspringIds);
            Offspring::whereIn('id', $deletedIds)->delete();

            // Note: Update logic for Lactation and HeatCycle based on date change is complex.
            // For a production system, you'd need to re-evaluate or update these records
            // if 'actual_delivery_date' changes significantly. We omit this complex re-sync here for brevity.

            return response()->json([
                'status' => 'success',
                'message' => 'Delivery updated successfully.',
                'data' => $delivery->load(['offspring', 'insemination.dam'])
            ]);
        });
    }

    /**
     * Remove the specified resource from storage (DELETE).
     */
    public function destroy(Request $request, Delivery $delivery)
    {
        // Security check
        if ($delivery->insemination->dam->farmer_id !== $request->user()->farmer->id) {
            return response()->json(['status' => 'error', 'message' => 'Record not found.'], 404);
        }

        return DB::transaction(function () use ($delivery) {
            $insemination = $delivery->insemination;
            $dam = $insemination->dam;

            // 1. Delete associated Offspring records
            $delivery->offspring()->delete();

            // 2. Delete the Delivery record
            $delivery->delete();

            // 3. Update Insemination status back to 'Confirmed Pregnant' (or find the next best status)
            // Assuming this is the only delivery record for this insemination.
            $insemination->update(['status' => 'Confirmed Pregnant']);

            // 4. Clean up the auto-generated Lactation record (based on start_date match)
            Lactation::where('dam_id', $dam->animal_id)
                ->where('start_date', $delivery->actual_delivery_date)
                ->where('status', 'Ongoing')
                ->delete();

            // 5. Delete the scheduled Heat Cycle (~80 days post-calving)
            $expectedNextHeat = Carbon::parse($delivery->actual_delivery_date)->addDays(80);
            HeatCycle::where('dam_id', $dam->animal_id)
                ->where('next_expected_date', $expectedNextHeat->toDateString())
                ->whereNull('observed_date')
                ->where('inseminated', false)
                ->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Delivery and related records deleted successfully.'
            ], 200);
        });
    }
}
