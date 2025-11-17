<?php

namespace App\Http\Controllers\Api\Farmer;

use App\Http\Controllers\Controller;
use App\Models\HealthReport;
use App\Models\HealthDiagnosis;
use App\Models\HealthTreatment;
use App\Models\Livestock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Exports\HealthReportExport;
use Maatwebsite\Excel\Facades\Excel;

class HealthRecordController extends Controller
{

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
    // SUMMARY: Dashboard stats
    // =================================================================
    public function summary(Request $request)
    {
        $farmerId = $request->user()->farmer->id;

        return response()->json([
            'status' => 'success',
            'data' => [
                'total_reports' => HealthReport::where('farmer_id', $farmerId)->count(),
                'active_cases' => HealthReport::where('farmer_id', $farmerId)->active()->count(),
                'emergencies' => HealthReport::where('farmer_id', $farmerId)->emergency()->count(),
                'today_reports' => HealthReport::where('farmer_id', $farmerId)->today()->count(),
                'recovered' => HealthReport::where('farmer_id', $farmerId)->where('status', 'Recovered')->count(),
                'deceased' => HealthReport::where('farmer_id', $farmerId)->where('status', 'Deceased')->count(),
                'pending_diagnosis' => HealthReport::where('farmer_id', $farmerId)->where('status', 'Pending Diagnosis')->count(),
            ]
        ]);
    }

    // =================================================================
    // ALERTS: Emergency + Overdue follow-ups
    // =================================================================
    public function alerts(Request $request)
    {
        $emergencies = HealthReport::where('farmer_id', $request->user()->farmer->id)
            ->emergency()
            ->with('animal')
            ->get();

        $overdue = HealthTreatment::overdueFollowUp()
            ->whereHas('healthReport', fn($q) => $q->where('farmer_id', $request->user()->farmer->id))
            ->with(['healthReport.animal', 'diagnosis'])
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'emergencies' => $emergencies,
                'overdue_treatments' => $overdue,
                'total_alerts' => $emergencies->count() + $overdue->count(),
            ]
        ]);
    }

    // =================================================================
    // DROPDOWNS: For health form
    // =================================================================
    public function dropdowns(Request $request)
    {
        return response()->json([
            'status' => 'success',
            'data' => [
                'animals' => Livestock::where('farmer_id', $request->user()->farmer->id)
                    ->select('animal_id as value',
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
