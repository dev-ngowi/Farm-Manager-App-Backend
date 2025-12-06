<?php

namespace App\Http\Controllers\Api\Veterinarian;

use App\Http\Controllers\Controller;
use App\Models\HealthTreatment;
use App\Models\HealthDiagnosis;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class HealthTreatmentController extends Controller
{

    /**
     * Helper: Restricts queries to treatments linked to the Vet's diagnoses.
     */
    private function baseQuery(Request $request)
    {
        $vetId = $request->user()->veterinarian->id;
        return HealthTreatment::whereHas('diagnosis', function ($q) use ($vetId) {
            $q->where('veterinarian_id', $vetId);
        })->with([
            'diagnosis:diagnosis_id,veterinarian_id,diagnosis_date',
            'healthReport.animal:animal_id,tag_number,name'
        ]);
    }

    // =================================================================
    // INDEX: List all treatments linked to the vet's diagnoses
    // =================================================================
    public function index(Request $request)
    {
        $query = $this->baseQuery($request)
            ->orderByDesc('treatment_date');

        // Optional Filters
        if ($request->filled('diagnosis_id')) {
            $query->where('diagnosis_id', $request->diagnosis_id);
        }
        if ($request->filled('health_id')) {
            $query->where('health_id', $request->health_id);
        }
        if ($request->boolean('ongoing')) {
            $query->ongoing();
        }
        if ($request->boolean('overdue_follow_up')) {
            $query->overdueFollowUp();
        }

        $treatments = $query->paginate(20);

        return response()->json([
            'status' => 'success',
            'data' => $treatments
        ]);
    }

    // =================================================================
    // SHOW: Display a single treatment record
    // =================================================================
    public function show(Request $request, $treatment_id)
    {
        $treatment = $this->baseQuery($request)
            ->findOrFail($treatment_id);

        return response()->json([
            'status' => 'success',
            'data' => $treatment
        ]);
    }

    // =================================================================
    // STORE: Create a new treatment record
    // =================================================================
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'diagnosis_id' => 'required|exists:health_diagnoses,diagnosis_id',
            'treatment_date' => 'required|date|before_or_equal:today',
            'drug_name' => 'required|string|max:255',
            'dosage' => 'required|string|max:255',
            'route' => 'required|in:IM,IV,SC,Oral,Topical,Other',
            'frequency' => 'required|string|max:255',
            'duration_days' => 'required|integer|min:1',
            'administered_by' => 'required|in:Veterinarian,Farmer',
            'cost' => 'nullable|numeric|min:0',
            'follow_up_date' => 'nullable|date|after_or_equal:treatment_date',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        // 1. Verify Diagnosis belongs to the current Vet
        $diagnosis = HealthDiagnosis::where('veterinarian_id', $request->user()->veterinarian->id)
            ->findOrFail($request->diagnosis_id);

        // 2. Extract health_id from diagnosis for the treatment record
        $healthId = $diagnosis->health_id;

        $treatment = null;
        DB::transaction(function () use ($request, $healthId, &$treatment) {
            $treatment = HealthTreatment::create([
                'diagnosis_id' => $request->diagnosis_id,
                'health_id' => $healthId,
                'treatment_date' => $request->treatment_date,
                'drug_name' => $request->drug_name,
                'dosage' => $request->dosage,
                'route' => $request->route,
                'frequency' => $request->frequency,
                'duration_days' => $request->duration_days,
                'administered_by' => $request->administered_by,
                'cost' => $request->cost,
                'follow_up_date' => $request->follow_up_date,
                'notes' => $request->notes,
                'outcome' => 'In Progress', // Default status for a new treatment
            ]);

            // 3. Update the main HealthReport status to 'Under Treatment'
            $diagnosis->healthReport->update(['status' => 'Under Treatment']);
        });


        return response()->json([
            'status' => 'success',
            'message' => 'Treatment record created successfully',
            'data' => $treatment
        ], 201);
    }

    // =================================================================
    // UPDATE: Update an existing treatment record
    // =================================================================
    public function update(Request $request, $treatment_id)
    {
        $treatment = $this->baseQuery($request)->findOrFail($treatment_id);

        $validator = Validator::make($request->all(), [
            'treatment_date' => 'sometimes|date|before_or_equal:today',
            'drug_name' => 'sometimes|string|max:255',
            'dosage' => 'sometimes|string|max:255',
            'route' => 'sometimes|in:IM,IV,SC,Oral,Topical,Other',
            'frequency' => 'sometimes|string|max:255',
            'duration_days' => 'sometimes|integer|min:1',
            'administered_by' => 'sometimes|in:Veterinarian,Farmer',
            'cost' => 'nullable|numeric|min:0',
            'outcome' => 'nullable|in:In Progress,Recovered,Deceased,Treatment Failed',
            'follow_up_date' => 'nullable|date|after_or_equal:treatment_date',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $treatment->update($request->except('diagnosis_id', 'health_id'));

        // If the outcome is final (Recovered, Deceased, Treatment Failed), update the main HealthReport status
        if (in_array($request->outcome, ['Recovered', 'Deceased'])) {
            $treatment->healthReport->update(['status' => $request->outcome]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Treatment record updated successfully',
            'data' => $treatment
        ]);
    }

    // =================================================================
    // DESTROY: Delete a treatment record
    // =================================================================
    public function destroy(Request $request, $treatment_id)
    {
        $treatment = $this->baseQuery($request)->findOrFail($treatment_id);

        // Before deleting, check if it was the *only* treatment for the diagnosis.
        // If so, you might want to revert the HealthReport status (optional logic).
        $diagnosis = $treatment->diagnosis;

        DB::transaction(function () use ($treatment, $diagnosis) {
            $treatment->delete();

            if ($diagnosis->treatments()->count() === 0) {
                $diagnosis->healthReport->update(['status' => 'Awaiting Treatment']);
            }
        });


        return response()->json([
            'status' => 'success',
            'message' => 'Treatment record deleted successfully'
        ]);
    }

    // =================================================================
    // DROPDOWNS: For treatment form
    // =================================================================
    public function dropdowns()
    {
        return response()->json([
            'status' => 'success',
            'data' => [
                'routes' => [
                    ['value' => 'IM', 'label' => 'Intramuscular'],
                    ['value' => 'IV', 'label' => 'Intravenous'],
                    ['value' => 'SC', 'label' => 'Subcutaneous'],
                    ['value' => 'Oral', 'label' => 'Oral'],
                    ['value' => 'Topical', 'label' => 'Topical'],
                    ['value' => 'Other', 'label' => 'Other'],
                ],
                'administered_by' => [
                    ['value' => 'Veterinarian', 'label' => 'Veterinarian'],
                    ['value' => 'Farmer', 'label' => 'Farmer'],
                ],
                'outcomes' => [
                    ['value' => 'In Progress', 'label' => 'In Progress'],
                    ['value' => 'Recovered', 'label' => 'Recovered'],
                    ['value' => 'Deceased', 'label' => 'Deceased'],
                    ['value' => 'Treatment Failed', 'label' => 'Treatment Failed'],
                ],
            ]
        ]);
    }
}
