<?php

namespace App\Http\Controllers\Api\Farmer;

use App\Http\Controllers\Controller;
use App\Models\PregnancyCheck;
use App\Models\Insemination;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PregnancyCheckController extends Controller
{
    /**
     * Display a listing of the resource (Pregnancy Checks).
     * Filters by result or insemination_id.
     */
    public function index(Request $request)
    {
        // Get the farmer's ID securely
        $farmerId = $request->user()->farmer->id;

        $query = PregnancyCheck::query()
            // Ensure all checks belong to inseminations tied to the current farmer
            ->whereHas('insemination.dam.farmer', fn($q) => $q->where('farmer_id', $farmerId))
            ->with([
                // Load insemination data, and nest the dam details
                'insemination' => fn($q) => $q->select('id', 'dam_id', 'insemination_date')
                    ->with(['dam:id,tag_number,name']),
                'vet:id,name', // Assuming Vet is related to a User model
            ]);

        // Apply filters
        if ($request->filled('result')) {
            $query->where('result', $request->result);
        }
        if ($request->filled('insemination_id')) {
            $query->where('insemination_id', $request->insemination_id);
        }

        $checks = $query->latest('check_date')->get();

        return response()->json([
            'status' => 'success',
            'data' => $checks,
            'meta' => ['total' => $checks->count()]
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'insemination_id' => 'required|exists:inseminations,id',
            'check_date' => 'required|date|before_or_equal:today', // Must be checked today or earlier
            // Rule to ensure check_date is after the insemination_date. Requires injecting Insemination model.
            // Simplified here, assuming frontend handles basic validity.
            'method' => 'required|in:Ultrasound,Palpation,Blood',
            'result' => 'required|in:Pregnant,Not Pregnant,Reabsorbed',
            'fetus_count' => 'nullable|integer|min:1', // Made nullable as it depends on result
            'expected_delivery_date' => 'nullable|date|after:check_date', // Made nullable
            'vet_id' => 'nullable|exists:users,id',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        // Find the insemination record belonging to the current farmer
        $insemination = Insemination::whereHas('dam.farmer', fn($q) => $q->where('farmer_id', $request->user()->farmer->id))
            ->findOrFail($request->insemination_id);

        // Check if `check_date` is after `insemination_date` manually if not using a custom rule
        if (Carbon::parse($request->check_date)->lessThanOrEqualTo(Carbon::parse($insemination->insemination_date))) {
             return response()->json(['status' => 'error', 'message' => 'Check date must be after the insemination date.'], 422);
        }

        return DB::transaction(function () use ($request, $insemination) {

            $isPregnant = $request->result === 'Pregnant';

            $check = PregnancyCheck::create([
                'insemination_id' => $insemination->id,
                'check_date' => $request->check_date,
                'method' => $request->method,
                'result' => $request->result,
                'fetus_count' => $isPregnant ? $request->fetus_count : null,
                'expected_delivery_date' => $isPregnant ? $request->expected_delivery_date : null,
                'vet_id' => $request->vet_id,
                'notes' => $request->notes,
            ]);

            // 1. Update Insemination Status and Due Date
            $insemination->update([
                'status' => match ($request->result) {
                    'Pregnant' => 'Confirmed Pregnant',
                    'Not Pregnant' => 'Not Pregnant',
                    'Reabsorbed' => 'Failed',
                    default => 'Pending', // Fallback
                },
                'expected_delivery_date' => $isPregnant ? $request->expected_delivery_date : null,
            ]);

            // 2. Update Heat Cycle if not pregnant
            if (!$isPregnant && $insemination->heatCycle) {
                // Assuming $insemination->heatCycle is loaded or accessible via relationship
                $insemination->heatCycle->update([
                    'inseminated' => false,
                    // Set next expected heat 21 days after the check date
                    'next_expected_date' => Carbon::parse($request->check_date)->addDays(21),
                ]);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Pregnancy check recorded successfully.',
                'data' => $check->load(['insemination.dam', 'vet'])
            ], 201);
        });
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, PregnancyCheck $check)
    {
        // Ensure the check belongs to the current farmer
        if ($check->insemination->dam->farmer_id !== $request->user()->farmer->id) {
            return response()->json(['status' => 'error', 'message' => 'Record not found.'], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $check->load(['insemination.dam', 'vet'])
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, PregnancyCheck $check)
    {
        // Security check
        if ($check->insemination->dam->farmer_id !== $request->user()->farmer->id) {
            return response()->json(['status' => 'error', 'message' => 'Record not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'check_date' => 'sometimes|required|date|before_or_equal:today',
            'method' => 'sometimes|required|in:Ultrasound,Palpation,Blood',
            'result' => 'sometimes|required|in:Pregnant,Not Pregnant,Reabsorbed',
            'fetus_count' => 'nullable|integer|min:1',
            'expected_delivery_date' => 'nullable|date|after:check_date',
            'vet_id' => 'nullable|exists:users,id',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        return DB::transaction(function () use ($request, $check) {
            $isPregnant = $request->input('result', $check->result) === 'Pregnant';

            // 1. Update the PregnancyCheck record
            $check->update([
                'check_date' => $request->check_date ?? $check->check_date,
                'method' => $request->method ?? $check->method,
                'result' => $request->result ?? $check->result,
                // Adjust dependent fields based on the resulting status
                'fetus_count' => $isPregnant ? $request->fetus_count : null,
                'expected_delivery_date' => $isPregnant ? $request->expected_delivery_date : null,
                'vet_id' => $request->vet_id ?? $check->vet_id,
                'notes' => $request->notes ?? $check->notes,
            ]);

            // 2. Determine new Insemination status
            $newResult = $request->input('result', $check->result);
            $newStatus = match ($newResult) {
                'Pregnant' => 'Confirmed Pregnant',
                'Not Pregnant' => 'Not Pregnant',
                'Reabsorbed' => 'Failed',
                default => 'Pending',
            };

            // 3. Update Insemination Status and Due Date
            $check->insemination->update([
                'status' => $newStatus,
                'expected_delivery_date' => $isPregnant ? $request->expected_delivery_date : null,
            ]);

            // 4. Update Heat Cycle if status changed to Not Pregnant/Failed
            if (!$isPregnant && $check->insemination->heatCycle) {
                $check->insemination->heatCycle->update([
                    'inseminated' => false,
                    'next_expected_date' => Carbon::parse($check->check_date)->addDays(21),
                ]);
            }
            // Note: If the animal was previously marked as Not Pregnant and is now being marked as Pregnant
            // the heat cycle update logic here might need more complexity, but this handles the basic flow.

            return response()->json([
                'status' => 'success',
                'message' => 'Pregnancy check updated successfully.',
                'data' => $check->load(['insemination.dam', 'vet'])
            ]);
        });
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, PregnancyCheck $check)
    {
        // Security check
        if ($check->insemination->dam->farmer_id !== $request->user()->farmer->id) {
            return response()->json(['status' => 'error', 'message' => 'Record not found.'], 404);
        }

        return DB::transaction(function () use ($check) {
            $insemination = $check->insemination;

            $check->delete();

            // After deleting a check, we need to reset the insemination status based on the remaining checks.
            // If this was the *last* check, the status must revert to 'Pending'.

            // 1. Get the most recent *remaining* check for the insemination
            $latestCheck = PregnancyCheck::where('insemination_id', $insemination->id)
                ->latest('check_date')
                ->first();

            if ($latestCheck) {
                // If other checks exist, update status based on the latest one
                $newStatus = match ($latestCheck->result) {
                    'Pregnant' => 'Confirmed Pregnant',
                    'Not Pregnant' => 'Not Pregnant',
                    'Reabsorbed' => 'Failed',
                    default => 'Pending',
                };
                $newDueDate = $latestCheck->result === 'Pregnant' ? $latestCheck->expected_delivery_date : null;

                $insemination->update([
                    'status' => $newStatus,
                    'expected_delivery_date' => $newDueDate,
                ]);

                // Update heat cycle if the new latest result is not pregnant
                if ($latestCheck->result !== 'Pregnant' && $insemination->heatCycle) {
                    $insemination->heatCycle->update(['inseminated' => false]);
                }

            } else {
                // 2. No other checks exist: Revert Insemination status to initial state
                $insemination->update([
                    'status' => 'Pending',
                    // Keep the original expected delivery date (calculated after insemination)
                ]);

                // Reset heat cycle if it was marked as inseminated
                 if ($insemination->heatCycle) {
                     $insemination->heatCycle->update(['inseminated' => true]); // Revert to inseminated until next check
                 }
            }


            return response()->json([
                'status' => 'success',
                'message' => 'Pregnancy check deleted successfully.'
            ], 200);
        });
    }
}
