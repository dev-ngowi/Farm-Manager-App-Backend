<?php

namespace App\Http\Controllers\Api\Vet;

use App\Http\Controllers\Controller;
use App\Models\VetAction;
use App\Models\DiagnosisResponse;
use App\Models\HealthReport;
use App\Models\Prescription;
use App\Models\RecoveryRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class VetActionController extends Controller
{
    // =================================================================
    // INDEX: List all actions for a diagnosis or health report
    // =================================================================
    public function index(Request $request, $diagnosis_id = null)
    {
        $vet = $request->user()->veterinarian;

        $query = VetAction::where('vet_id', $vet->vet_id)
            ->with([
                'diagnosis.healthReport.animal',
                'diagnosis.healthReport.farmer.user',
                'prescription',
                'latestRecovery'
            ])
            ->latest('action_date');

        if ($diagnosis_id) {
            $query->where('diagnosis_id', $diagnosis_id);
        }

        $actions = $query->paginate(15);

        return response()->json([
            'status' => 'success',
            'data' => $actions
        ]);
    }

    // =================================================================
    // STORE: Add new vet action (treatment, vaccination, etc.)
    // =================================================================
    public function store(Request $request, $diagnosis_id)
    {
        $vet = $request->user()->veterinarian;
        $diagnosis = DiagnosisResponse::where('vet_id', $vet->vet_id)
            ->findOrFail($diagnosis_id);

        $validator = Validator::make($request->all(), [
            'action_type' => 'required|in:Treatment,Vaccination,Prescription,Surgery,Advisory,Consultation',
            'action_location' => 'required|in:Clinic,Farm Visit,Remote Consultation',
            'action_date' => 'required|date|before:tomorrow',
            'action_time' => 'required|date_format:H:i',
            'medicine_name' => 'nullable|string|max:255',
            'dosage' => 'nullable|string|max:100',
            'administration_route' => 'nullable|string|max:100',
            'vaccine_name' => 'nullable|string|max:255',
            'vaccine_batch_number' => 'nullable|string|max:100',
            'next_vaccination_due' => 'nullable|date|after:today',
            'treatment_cost' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',

            // Prescription (for Prescription or Treatment)
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
            $action = VetAction::create([
                'health_id' => $diagnosis->health_id,
                'diagnosis_id' => $diagnosis->diagnosis_id,
                'vet_id' => $vet->vet_id,
                'action_type' => $request->action_type,
                'action_date' => $request->action_date,
                'action_time' => $request->action_date . ' ' . $request->action_time,
                'action_location' => $request->action_location,
                'medicine_name' => $request->medicine_name,
                'dosage' => $request->dosage,
                'administration_route' => $request->administration_route,
                'vaccine_name' => $request->vaccine_name,
                'vaccine_batch_number' => $request->vaccine_batch_number,
                'vaccination_date' => $request->action_type === 'Vaccination' ? $request->action_date : null,
                'next_vaccination_due' => $request->next_vaccination_due,
                'treatment_cost' => $request->treatment_cost ?? 0,
                'payment_status' => ($request->treatment_cost ?? 0) > 0 ? 'Pending' : 'Waived',
                'notes' => $request->notes,
            ]);

            // Create Prescription if provided
            if ($request->filled('prescription')) {
                Prescription::create([
                    'action_id' => $action->action_id,
                    'medicine_name' => $request->prescription['medicine_name'],
                    'dosage' => $request->prescription['dosage'],
                    'frequency' => $request->prescription['frequency'],
                    'duration_days' => $request->prescription['duration_days'],
                    'issued_date' => $request->action_date,
                    'instructions' => $request->prescription['instructions'] ?? null,
                ]);
            }

            // Update Health Report Status
            $newStatus = match ($request->action_type) {
                'Treatment', 'Surgery' => 'Under Treatment',
                'Prescription' => 'Awaiting Treatment',
                'Vaccination' => 'Vaccinated',
                'Advisory', 'Consultation' => 'Under Monitoring',
                default => $diagnosis->healthReport->status
            };

            $diagnosis->healthReport->update(['status' => $newStatus]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Hatua imerekodiwa kikamilifu',
                'data' => $action->load('prescription')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Imeshindwa kurekodi hatua'], 500);
        }
    }

    // =================================================================
    // UPDATE: Edit action (e.g., update cost, mark paid)
    // =================================================================
    public function update(Request $request, $action_id)
    {
        $vet = $request->user()->veterinarian;
        $action = VetAction::where('vet_id', $vet->vet_id)->findOrFail($action_id);

        $validator = Validator::make($request->all(), [
            'treatment_cost' => 'sometimes|numeric|min:0',
            'payment_status' => 'sometimes|in:Pending,Paid,Waived',
            'notes' => 'sometimes|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $action->update($request->only(['treatment_cost', 'payment_status', 'notes']));

        return response()->json([
            'status' => 'success',
            'message' => 'Hatua imesasishwa',
            'data' => $action
        ]);
    }

    // =================================================================
    // MARK PAID
    // =================================================================
    public function markPaid(Request $request, $action_id)
    {
        $vet = $request->user()->veterinarian;
        $action = VetAction::where('vet_id', $vet->vet_id)->findOrFail($action_id);

        if ($action->treatment_cost <= 0) {
            return response()->json(['status' => 'error', 'message' => 'Hakuna gharama'], 400);
        }

        $action->update(['payment_status' => 'Paid']);

        return response()->json([
            'status' => 'success',
            'message' => 'Malipo yamepokewa'
        ]);
    }

    // =================================================================
    // RECORD RECOVERY
    // =================================================================
    public function recordRecovery(Request $request, $action_id)
    {
        $vet = $request->user()->veterinarian;
        $action = VetAction::where('vet_id', $vet->vet_id)->findOrFail($action_id);

        $validator = Validator::make($request->all(), [
            'recovery_status' => 'required|in:Improving,Stable,Worsening,Fully Recovered',
            'observations' => 'required|string',
            'next_checkup_date' => 'nullable|date|after:today',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $recovery = RecoveryRecord::create([
            'action_id' => $action->action_id,
            'recorded_by' => $vet->user_id,
            'recovery_status' => $request->recovery_status,
            'observations' => $request->observations,
            'recorded_at' => now(),
            'next_checkup_date' => $request->next_checkup_date,
        ]);

        // Update Health Report if fully recovered
        if ($request->recovery_status === 'Fully Recovered') {
            $action->healthReport->update(['status' => 'Recovered']);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Hali ya mnyama imerekodiwa',
            'data' => $recovery
        ], 201);
    }

    // =================================================================
    // PDF: Download Action Receipt
    // =================================================================
    public function downloadPdf($action_id)
    {
        $vet = request()->user()->veterinarian;
        $action = VetAction::where('vet_id', $vet->vet_id)
            ->with(['diagnosis.healthReport.animal', 'diagnosis.healthReport.farmer.user', 'prescription'])
            ->findOrFail($action_id);

        $pdf = Pdf::loadView('pdf.vet-action-receipt', compact('action'))
            ->setPaper('a4', 'portrait')
            ->setOptions(['defaultFont' => 'DejaVu Sans']);

        $filename = "Risiti-Hatua-{$action->action_type_swahili}-" .
            Carbon::parse($action->action_date)->format('d-m-Y') . ".pdf";

        return $pdf->download($filename);
    }

    // =================================================================
    // SUMMARY: Vet's action KPIs
    // =================================================================
    public function summary(Request $request)
    {
        $vet = $request->user()->veterinarian;

        $today = VetAction::where('vet_id', $vet->vet_id)->today()->count();
        $unpaid = VetAction::where('vet_id', $vet->vet_id)->unpaid()->sum('treatment_cost');
        $farmVisits = VetAction::where('vet_id', $vet->vet_id)->farmVisits()->count();
        $vaccinations = VetAction::where('vet_id', $vet->vet_id)
            ->where('action_type', 'Vaccination')
            ->whereNotNull('next_vaccination_due')
            ->where('next_vaccination_due', '<=', now()->addDays(7))
            ->count();

        return response()->json([
            'status' => 'success',
            'data' => [
                'actions_today' => $today,
                'unpaid_amount' => round($unpaid, 2),
                'farm_visits_this_month' => $farmVisits,
                'vaccines_due_soon' => $vaccinations,
            ]
        ]);
    }
}
