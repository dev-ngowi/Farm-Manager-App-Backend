<?php

namespace App\Http\Controllers\Api\Vet;

use App\Http\Controllers\Controller;
use App\Models\DiagnosisResponse;
use App\Models\VetAction;
use App\Models\HealthReport;
use App\Models\Prescription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class DiagnosisController extends Controller
{
    // =================================================================
    // INDEX: List health reports assigned to this vet (or open for response)
    // =================================================================
    public function index(Request $request)
    {
        $vet = $request->user()->veterinarian;

        $reports = HealthReport::where(function ($q) use ($vet) {
            $q->where('assigned_vet_id', $vet->vet_id)
              ->orWhereNull('assigned_vet_id');
        })
        ->whereIn('status', ['Submitted', 'Under Diagnosis'])
        ->with(['animal.tag_number', 'animal.name', 'farmer.user'])
        ->latest('reported_at')
        ->paginate(15);

        return response()->json([
            'status' => 'success',
            'data' => $reports
        ]);
    }

    // =================================================================
    // RESPOND: Submit diagnosis + actions + prescription
    // =================================================================
    public function respond(Request $request, $health_id)
    {
        $vet = $request->user()->veterinarian;
        $report = HealthReport::where('health_id', $health_id)
            ->where(function ($q) use ($vet) {
                $q->where('assigned_vet_id', $vet->vet_id)
                  ->orWhereNull('assigned_vet_id');
            })
            ->firstOrFail();

        $validator = Validator::make($request->all(), [
            'suspected_disease' => 'required|string|max:255',
            'diagnosis_notes' => 'required|string',
            'recommended_tests' => 'nullable|string',
            'prognosis' => 'required|in:Excellent,Good,Fair,Poor,Grave',
            'estimated_recovery_days' => 'nullable|integer|min:0',
            'follow_up_required' => 'boolean',
            'follow_up_date' => 'nullable|date|after:today',

            // Actions
            'actions' => 'required|array|min:1',
            'actions.*.action_type' => 'required|in:Treatment,Vaccination,Prescription,Surgery,Advisory,Consultation',
            'actions.*.medicine_name' => 'nullable|string',
            'actions.*.dosage' => 'nullable|string',
            'actions.*.administration_route' => 'nullable|string',
            'actions.*.vaccine_name' => 'nullable|string',
            'actions.*.vaccine_batch_number' => 'nullable|string',
            'actions.*.next_vaccination_due' => 'nullable|date|after:today',
            'actions.*.treatment_cost' => 'nullable|numeric|min:0',
            'actions.*.action_location' => 'required|in:Clinic,Farm Visit,Remote Consultation',
            'actions.*.notes' => 'nullable|string',

            // Prescription (if any)
            'prescription' => 'nullable|array',
            'prescription.medicine_name' => 'required_with:prescription|string',
            'prescription.dosage' => 'required_with:prescription|string',
            'prescription.frequency' => 'required_with:prescription|string',
            'prescription.duration_days' => 'required_with:prescription|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();

        try {
            // 1. Create Diagnosis
            $diagnosis = DiagnosisResponse::create([
                'health_id' => $report->health_id,
                'vet_id' => $vet->vet_id,
                'suspected_disease' => $request->suspected_disease,
                'diagnosis_notes' => $request->diagnosis_notes,
                'recommended_tests' => $request->recommended_tests,
                'prognosis' => $request->prognosis,
                'estimated_recovery_days' => $request->estimated_recovery_days,
                'diagnosis_date' => now(),
                'follow_up_required' => $request->boolean('follow_up_required', false),
                'follow_up_date' => $request->follow_up_date,
            ]);

            // 2. Create Actions
            foreach ($request->actions as $actionData) {
                $action = VetAction::create([
                    'health_id' => $report->health_id,
                    'diagnosis_id' => $diagnosis->diagnosis_id,
                    'vet_id' => $vet->vet_id,
                    'action_type' => $actionData['action_type'],
                    'action_date' => now()->format('Y-m-d'),
                    'action_time' => now()->format('H:i'),
                    'action_location' => $actionData['action_location'],
                    'medicine_name' => $actionData['medicine_name'] ?? null,
                    'dosage' => $actionData['dosage'] ?? null,
                    'administration_route' => $actionData['administration_route'] ?? null,
                    'vaccine_name' => $actionData['vaccine_name'] ?? null,
                    'vaccine_batch_number' => $actionData['vaccine_batch_number'] ?? null,
                    'vaccination_date' => $actionData['action_type'] === 'Vaccination' ? now() : null,
                    'next_vaccination_due' => $actionData['next_vaccination_due'] ?? null,
                    'treatment_cost' => $actionData['treatment_cost'] ?? 0,
                    'payment_status' => ($actionData['treatment_cost'] ?? 0) > 0 ? 'Pending' : 'Waived',
                    'notes' => $actionData['notes'] ?? null,
                ]);

                // 3. Create Prescription if provided
                if ($request->filled('prescription') && in_array($actionData['action_type'], ['Prescription', 'Treatment'])) {
                    Prescription::create([
                        'action_id' => $action->action_id,
                        'medicine_name' => $request->prescription['medicine_name'],
                        'dosage' => $request->prescription['dosage'],
                        'frequency' => $request->prescription['frequency'],
                        'duration_days' => $request->prescription['duration_days'],
                        'issued_date' => now(),
                        'instructions' => $request->prescription['instructions'] ?? null,
                    ]);
                }
            }

            // 4. Update Health Report
            $report->update([
                'status' => 'Under Treatment',
                'assigned_vet_id' => $vet->vet_id,
                'priority' => $request->prognosis === 'Grave' ? 'Emergency' : $report->priority,
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Utambuzi na hatua zimepokewa. Mkulima ataarifiwa.',
                'data' => $diagnosis->load('veterinarian.user', 'vetActions.prescription')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Imeshindwa kusubmit'], 500);
        }
    }

    // =================================================================
    // SHOW: View full diagnosis + actions
    // =================================================================
    public function show($diagnosis_id)
    {
        $vet = request()->user()->veterinarian;

        $diagnosis = DiagnosisResponse::where('vet_id', $vet->vet_id)
            ->with([
                'healthReport.animal',
                'healthReport.farmer.user',
                'veterinarian.user',
                'vetActions.prescription',
                'vetActions.recoveryRecords'
            ])
            ->findOrFail($diagnosis_id);

        return response()->json([
            'status' => 'success',
            'data' => $diagnosis
        ]);
    }

    // =================================================================
    // FOLLOW-UP: Schedule or update follow-up
    // =================================================================
    public function followUp(Request $request, $diagnosis_id)
    {
        $vet = $request->user()->veterinarian;
        $diagnosis = DiagnosisResponse::where('vet_id', $vet->vet_id)->findOrFail($diagnosis_id);

        $validator = Validator::make($request->all(), [
            'follow_up_date' => 'required|date|after:today',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $diagnosis->update([
            'follow_up_required' => true,
            'follow_up_date' => $request->follow_up_date,
        ]);

        // Optional: Create advisory action
        VetAction::create([
            'health_id' => $diagnosis->health_id,
            'diagnosis_id' => $diagnosis->diagnosis_id,
            'vet_id' => $vet->vet_id,
            'action_type' => 'Advisory',
            'action_date' => $request->follow_up_date,
            'action_location' => 'Follow-up',
            'notes' => $request->notes ?? 'Ufuatiliaji wa afya umepangwa.',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Ufuatiliaji umepangwa kwa ' . Carbon::parse($request->follow_up_date)->format('d/m/Y')
        ]);
    }

    // =================================================================
    // PDF: Download Diagnosis + Treatment Receipt
    // =================================================================
    public function downloadPdf($diagnosis_id)
    {
        $vet = request()->user()->veterinarian;
        $diagnosis = DiagnosisResponse::where('vet_id', $vet->vet_id)
            ->with(['healthReport.animal', 'healthReport.farmer.user', 'vetActions.prescription'])
            ->findOrFail($diagnosis_id);

        $pdf = Pdf::loadView('pdf.diagnosis-receipt', compact('diagnosis'))
            ->setPaper('a4', 'portrait')
            ->setOptions(['defaultFont' => 'DejaVu Sans']);

        $filename = "Risiti-Utambuzi-{$diagnosis->healthReport->animal->tag_number}-" .
            now()->format('d-m-Y') . ".pdf";

        return $pdf->download($filename);
    }

    // =================================================================
    // ALERTS: Urgent cases for vet
    // =================================================================
    public function alerts(Request $request)
    {
        $vet = $request->user()->veterinarian;

        $grave = DiagnosisResponse::grave()
            ->where('vet_id', $vet->vet_id)
            ->with('healthReport.animal')
            ->count();

        $followUps = DiagnosisResponse::needsFollowUp()
            ->where('vet_id', $vet->vet_id)
            ->count();

        $unpaid = VetAction::unpaid()
            ->where('vet_id', $vet->vet_id)
            ->sum('treatment_cost');

        return response()->json([
            'status' => 'success',
            'data' => [
                'grave_cases' => $grave,
                'follow_ups_due' => $followUps,
                'unpaid_amount' => round($unpaid, 2),
            ]
        ]);
    }
}
