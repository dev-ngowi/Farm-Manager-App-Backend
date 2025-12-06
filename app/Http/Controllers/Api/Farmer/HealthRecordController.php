<?php

namespace App\Http\Controllers\Api\Farmer;

use App\Http\Controllers\Controller;
use App\Models\HealthReport;
use App\Models\HealthTreatment;
use App\Models\Livestock;
use App\Models\Prescription; // Added
use App\Models\VaccinationSchedule; // Added
use App\Models\VetAppointment; // Added
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Exports\HealthReportExport;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon; // Added

class HealthRecordController extends Controller
{

    // =================================================================
    // DASHBOARD: Consolidated Health & Vet Activity Summary for Farmer
    // =================================================================
    public function dashboard(Request $request)
    {
        $farmerId = $request->user()->farmer->id;

        // 1. Core Health Summary (KPIs)
        $summary = [
            'total_reports' => HealthReport::where('farmer_id', $farmerId)->count(),
            'active_cases' => HealthReport::where('farmer_id', $farmerId)->active()->count(),
            'emergencies' => HealthReport::where('farmer_id', $farmerId)->emergency()->count(),
            'pending_diagnosis' => HealthReport::where('farmer_id', $farmerId)->where('status', 'Pending Diagnosis')->count(),
            'under_treatment' => HealthReport::where('farmer_id', $farmerId)->where('status', 'Under Treatment')->count(),
        ];

        // 2. Recent Issues/Reports (HealthReports)
        $recentIssues = HealthReport::where('farmer_id', $farmerId)
            ->with(['animal' => fn($q) => $q->select('animal_id', 'tag_number', 'name')])
            ->active() // Only show cases that are not recovered/deceased
            ->orderByDesc('report_date')
            ->take(5)
            ->get(['health_id', 'animal_id', 'report_date', 'symptoms', 'priority', 'status']);

        // 3. Alerts (Emergencies & Overdue Follow-ups)
        $alerts = $this->getAlertsData($farmerId);

        // 4. Vet Activity Summary (from separate method)
        $vetActivity = $this->getVetActivityData($farmerId);


        return response()->json([
            'status' => 'success',
            'data' => [
                'summary' => $summary,
                'recent_issues' => $recentIssues,
                'alerts' => $alerts,
                'vet_activity' => $vetActivity,
            ]
        ]);
    }

    // =================================================================
    // VET ACTIVITY DATA (Helper function for dashboard)
    // =================================================================
    private function getVetActivityData($farmerId)
    {
        // Upcoming Appointments (Today/Next 7 days)
        $upcomingAppointments = VetAppointment::where('farmer_id', $farmerId)
            ->where('status', 'Scheduled')
            ->where('appointment_date', '>=', today())
            ->where('appointment_date', '<=', today()->addDays(7))
            ->with(['veterinarian:id,name,phone', 'animal:animal_id,tag_number'])
            ->orderBy('appointment_date')
            ->take(3)
            ->get();

        // Active Prescriptions (Currently being administered)
        $activePrescriptions = Prescription::where('farmer_id', $farmerId)
            ->where('prescription_status', 'Active')
            ->where('end_date', '>=', today())
            ->with(['animal:animal_id,tag_number', 'veterinarian:id,name'])
            ->orderBy('end_date', 'asc')
            ->take(5)
            ->get(['prescription_id', 'animal_id', 'drug_name_custom', 'end_date', 'dosage', 'frequency']);

        // Upcoming Vaccinations (Next 30 days)
        $upcomingVaccinations = VaccinationSchedule::whereHas('animal', fn($q) => $q->where('farmer_id', $farmerId))
            ->upcoming()
            ->where('scheduled_date', '<=', today()->addDays(30))
            ->with(['animal:animal_id,tag_number'])
            ->orderBy('scheduled_date', 'asc')
            ->take(5)
            ->get(['schedule_id', 'animal_id', 'vaccine_name', 'scheduled_date', 'disease_prevented']);

        return [
            'upcoming_appointments' => $upcomingAppointments,
            'active_prescriptions' => $activePrescriptions,
            'upcoming_vaccinations' => $upcomingVaccinations,
        ];
    }

    // =================================================================
    // ALERTS: Emergency + Overdue follow-ups
    // =================================================================
    public function alerts(Request $request)
    {
        $farmerId = $request->user()->farmer->id;
        $alertsData = $this->getAlertsData($farmerId);

        return response()->json([
            'status' => 'success',
            'data' => $alertsData
        ]);
    }

    private function getAlertsData($farmerId)
    {
        $emergencies = HealthReport::where('farmer_id', $farmerId)
            ->emergency()
            ->with('animal:animal_id,tag_number,name')
            ->get(['health_id', 'animal_id', 'report_date', 'symptoms', 'priority']);

        $overdue = HealthTreatment::overdueFollowUp()
            ->whereHas('healthReport', fn($q) => $q->where('farmer_id', $farmerId))
            ->with(['healthReport.animal:animal_id,tag_number,name'])
            ->get(['treatment_id', 'follow_up_date', 'drug_name', 'diagnosis_id']);

        return [
            'emergencies' => $emergencies,
            'overdue_treatments' => $overdue,
            'total_alerts' => $emergencies->count() + $overdue->count(),
        ];
    }

    // =================================================================
    // INDEX: List all health reports for farmer
    // =================================================================
    public function index(Request $request)
    {
        $query = HealthReport::where('farmer_id', $request->user()->farmer->id)
            ->with([
                'animal' => fn($q) => $q->select('animal_id', 'tag_number', 'name', 'sex'),
                'diagnoses' => fn($q) => $q->with('vet:id,name,phone')->latest('diagnosis_date'),
                'treatments' => fn($q) => $q->latest('treatment_date')->take(3)
            ])
            ->withCount(['diagnoses', 'treatments'])
            ->orderByDesc('report_date');

        // Filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }
        if ($request->boolean('emergency')) {
            $query->emergency();
        }
        if ($request->boolean('today')) {
            $query->today();
        }
        if ($request->boolean('this_week')) {
            $query->thisWeek();
        }
        if ($request->filled('animal_id')) {
            $query->where('animal_id', $request->animal_id);
        }

        $reports = $query->paginate(20);

        return response()->json([
            'status' => 'success',
            'data' => $reports
        ]);
    }

    // =================================================================
    // SHOW: Single health report with full history
    // =================================================================
    public function show(Request $request, $health_id)
    {
        $report = HealthReport::where('farmer_id', $request->user()->farmer->id)
            ->with([
                'animal',
                'diagnoses' => fn($q) => $q->with(['vet', 'treatments'])->orderByDesc('diagnosis_date'),
                'treatments' => fn($q) => $q->orderByDesc('treatment_date'),
                'media'
            ])
            ->findOrFail($health_id);

        return response()->json([
            'status' => 'success',
            'data' => $report
        ]);
    }

    // =================================================================
    // STORE: Create new health report + upload photos/videos
    // =================================================================
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'animal_id' => 'required|exists:livestock,animal_id',
            'symptoms' => 'required|string|max:1000',
            'symptom_onset_date' => 'required|date|before_or_equal:today',
            'severity' => 'required|in:Mild,Moderate,Severe,Critical',
            'priority' => 'required|in:Low,Medium,High,Emergency',
            'location_latitude' => 'nullable|numeric|between:-90,90',
            'location_longitude' => 'nullable|numeric|between:-180,180',
            'notes' => 'nullable|string',
            'photos' => 'nullable|array|max:5',
            'photos.*' => 'file|mimes:jpeg,png,jpg,mp4,3gp|max:15360', // 15MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Verify animal belongs to farmer
        $animal = Livestock::where('animal_id', $request->animal_id)
            ->where('farmer_id', $request->user()->farmer->id)
            ->firstOrFail();

        DB::transaction(function () use ($request, $animal, &$report) {
            $report = HealthReport::create([
                'farmer_id' => $animal->farmer_id,
                'animal_id' => $animal->animal_id,
                'symptoms' => $request->symptoms,
                'symptom_onset_date' => $request->symptom_onset_date,
                'severity' => $request->severity,
                'priority' => $request->priority,
                'report_date' => now(),
                'location_latitude' => $request->location_latitude,
                'location_longitude' => $request->location_longitude,
                'status' => 'Pending Diagnosis',
                'notes' => $request->notes,
            ]);

            // Upload media
            if ($request->hasFile('photos')) {
                foreach ($request->file('photos') as $file) {
                    $report->addMedia($file)
                        ->usingName('health_media_' . now()->timestamp)
                        ->toMediaCollection('health_media');
                }
            }
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Health report submitted successfully',
            'data' => $report->load('animal', 'media')
        ], 201);
    }

    // =================================================================
    // UPDATE: Update report status or notes
    // =================================================================
    public function update(Request $request, $health_id)
    {
        $report = HealthReport::where('farmer_id', $request->user()->farmer->id)
            ->findOrFail($health_id);

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:Pending Diagnosis,Under Diagnosis,Awaiting Treatment,Under Treatment,Recovered,Deceased',
            'priority' => 'sometimes|in:Low,Medium,High,Emergency',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $report->update($request->only(['status', 'priority', 'notes']));

        return response()->json([
            'status' => 'success',
            'message' => 'Health report updated',
            'data' => $report
        ]);
    }

    // =================================================================
    // DESTROY: Delete report (only if no diagnosis/treatment)
    // =================================================================
    public function destroy(Request $request, $health_id)
    {
        $report = HealthReport::where('farmer_id', $request->user()->farmer->id)
            ->findOrFail($health_id);

        if ($report->diagnoses()->exists() || $report->treatments()->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot delete report with diagnosis or treatment records'
            ], 400);
        }

        $report->clearMediaCollection('health_media');
        $report->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Health report deleted'
        ]);
    }

    // =================================================================
    // TREATMENTS: List all treatments for farmer
    // =================================================================
    public function treatmentsIndex(Request $request)
    {
        $farmerId = $request->user()->farmer->id;

        $query = HealthTreatment::whereHas('healthReport', fn($q) => $q->where('farmer_id', $farmerId))
            ->with([
                // Get animal info through the healthReport relationship
                'healthReport.animal:animal_id,tag_number,name,sex',
                // Get the vet who made the diagnosis (and thus the treatment)
                'diagnosis.vet:id,name,phone'
            ])
            ->orderByDesc('treatment_date');

        // Filters for list
        if ($request->boolean('overdue')) {
            $query->overdueFollowUp(); // Scope from HealthTreatment Model
        }

        $treatments = $query->paginate(20);

        return response()->json([
            'status' => 'success',
            'data' => $treatments
        ]);
    }

    // =================================================================
    // SHOW TREATMENT: Single treatment detail
    // =================================================================
    public function treatmentShow(Request $request, $treatment_id)
    {
        $treatment = HealthTreatment::where('treatment_id', $treatment_id)
            ->whereHas('healthReport', fn($q) => $q->where('farmer_id', $request->user()->farmer->id))
            ->with([
                'healthReport.animal:animal_id,tag_number,name,sex,health_id', // Add fields needed for report link
                'healthReport:health_id,status,symptoms', // Include basic report fields
                'diagnosis.vet:id,name,phone',
            ])
            ->firstOrFail();

        return response()->json([
            'status' => 'success',
            'data' => $treatment
        ]);
    }

    // =================================================================
    // SUMMARY: Dashboard stats (REMOVED: consolidated into dashboard method)
    // =================================================================
    // public function summary(Request $request)
    // {
    //     // ... code here
    // }

    // =================================================================
    // DROPDOWNS: For health form
    // =================================================================
    public function dropdowns(Request $request)
    {
        return response()->json([
            'status' => 'success',
            'data' => [
                'animals' => Livestock::where('farmer_id', $request->user()->farmer->id)
                    ->select(
                        'animal_id as value',
                        DB::raw("CONCAT(tag_number, ' - ', COALESCE(name, 'No Name')) as label")
                    )
                    ->active()
                    ->orderBy('tag_number')
                    ->get(),

                'severities' => [
                    ['value' => 'Mild', 'label' => 'Mild'],
                    ['value' => 'Moderate', 'label' => 'Moderate'],
                    ['value' => 'Severe', 'label' => 'Severe'],
                    ['value' => 'Critical', 'label' => 'Critical'],
                ],

                'priorities' => [
                    ['value' => 'Low', 'label' => 'Low'],
                    ['value' => 'Medium', 'label' => 'Medium'],
                    ['value' => 'High', 'label' => 'High'],
                    ['value' => 'Emergency', 'label' => 'Emergency'],
                ],
            ]
        ]);
    }
    // =================================================================
    // DOWNLOAD PDF: Single report
    // =================================================================
    public function downloadPdf(Request $request, $health_id)
    {
        $report = HealthReport::where('farmer_id', $request->user()->farmer->id)
            ->with(['animal', 'diagnoses.vet', 'media'])
            ->findOrFail($health_id);

        $farmer = $request->user()->farmer;

        // Fix image paths for PDF
        foreach ($report->media as $media) {
            $media->path = storage_path('app/public/' . $media->getRawOriginal('path'));
        }

        $pdf = Pdf::loadView('pdf.health-report', compact('report', 'farmer'))
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'defaultFont' => 'DejaVu Sans',
                'isHtml5ParserEnabled' => true,
                'isPhpEnabled' => true,
                'isRemoteEnabled' => true,
                'tempDir' => storage_path('app/pdf-temp'),
            ]);

        $filename = "Ripoti-Afya-{$report->animal->tag_number}-" . now()->format('Y-m-d') . ".pdf";

        return $pdf->download($filename);
    }

    // =================================================================
    // DOWNLOAD EXCEL: All reports
    // =================================================================
    public function downloadExcel(Request $request)
    {
        $filename = "Ripoti-Za-Afya-Zote-" . now()->format('Y-m-d') . ".xlsx";

        return Excel::download(
            new HealthReportExport($request->user()->farmer->id),
            $filename
        );
    }

    // =================================================================
    // DOWNLOAD PDF ALL: All reports (multi-page)
    // =================================================================
    public function downloadAllPdf(Request $request)
    {
        $reports = HealthReport::where('farmer_id', $request->user()->farmer->id)
            ->with(['animal', 'diagnoses', 'media'])
            ->orderByDesc('report_date')
            ->get();

        $farmer = $request->user()->farmer;

        $pdf = Pdf::loadView('pdf.health-report-all', compact('reports', 'farmer'))
            ->setPaper('a4', 'portrait')
            ->setOptions(['defaultFont' => 'DejaVu Sans']);

        $filename = "Ripoti-Zote-Za-Afya-" . now()->format('Y-m-d') . ".pdf";

        return $pdf->download($filename);
    }
}
