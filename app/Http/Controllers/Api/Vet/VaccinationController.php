<?php

namespace App\Http\Controllers\Api\Vet;

use App\Http\Controllers\Controller;
use App\Models\VaccinationSchedule;
use App\Models\Livestock;
use App\Models\Veterinarian;
use App\Models\VetAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class VaccinationController extends Controller
{
    // =================================================================
    // VET: List all vaccination schedules (calendar + list)
    // =================================================================
    public function index(Request $request)
    {
        $vet = $request->user()->veterinarian;

        $query = VaccinationSchedule::with(['animal.farmer.user', 'veterinarian.user'])
            ->where('vet_id', $vet->vet_id)
            ->orWhereNull('vet_id') // Allow unassigned
            ->latest('scheduled_date');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->boolean('today')) {
            $query->today();
        }
        if ($request->boolean('upcoming')) {
            $query->upcoming();
        }

        $schedules = $query->paginate(20);

        return response()->json([
            'status' => 'success',
            'data' => $schedules
        ]);
    }

    // =================================================================
    // VET: Record vaccination (after appointment or farm visit)
    // =================================================================
    public function store(Request $request)
    {
        $vet = $request->user()->veterinarian;

        $validator = Validator::make($request->all(), [
            'animal_id' => 'required|exists:livestock,animal_id',
            'vaccine_name' => 'required|string|max:255',
            'disease_prevented' => 'required|string|max:255',
            'scheduled_date' => 'required|date|after_or_equal:today',
            'batch_number' => 'nullable|string|max:100',
            'expiry_date' => 'nullable|date|after:today',
            'dose_ml' => 'nullable|numeric|min:0',
            'administration_route' => 'nullable|in:Intramuscular,Subcutaneous,Oral,Nasal',
            'notes' => 'nullable|string',
            'action_id' => 'nullable|exists:vet_actions,action_id',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        // Validate animal belongs to farmer in vet's service area
        $animal = Livestock::with('farmer')->findOrFail($request->animal_id);
        // Optional: Add service area check here

        DB::beginTransaction();

        try {
            $schedule = VaccinationSchedule::updateOrCreate(
                [
                    'animal_id' => $request->animal_id,
                    'vaccine_name' => $request->vaccine_name,
                    'scheduled_date' => $request->scheduled_date,
                ],
                [
                    'vet_id' => $vet->vet_id,
                    'disease_prevented' => $request->disease_prevented,
                    'status' => 'Pending',
                    'batch_number' => $request->batch_number,
                    'expiry_date' => $request->expiry_date,
                    'dose_ml' => $request->dose_ml,
                    'administration_route' => $request->administration_route,
                    'notes' => $request->notes,
                    'action_id' => $request->action_id,
                ]
            );

            // Link to VetAction if provided
            if ($request->action_id) {
                VetAction::where('action_id', $request->action_id)
                    ->update(['vaccine_name' => $request->vaccine_name]);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Ratiba ya chanjo imeandikishwa',
                'data' => $schedule->load('animal')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Imeshindwa kurekodi'], 500);
        }
    }

    // =================================================================
    // VET: Mark vaccination as completed
    // =================================================================
    public function complete(Request $request, $schedule_id)
    {
        $vet = $request->user()->veterinarian;
        $schedule = VaccinationSchedule::where('vet_id', $vet->vet_id)
            ->orWhereNull('vet_id')
            ->findOrFail($schedule_id);

        if ($schedule->status !== 'Pending') {
            return response()->json(['status' => 'error', 'message' => 'Chanjo tayari imerekodiwa'], 400);
        }

        $validator = Validator::make($request->all(), [
            'actual_date' => 'required|date|after_or_equal:' . $schedule->scheduled_date,
            'batch_number' => 'required|string',
            'dose_ml' => 'required|numeric',
            'next_due_date' => 'nullable|date|after:actual_date',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $schedule->update([
            'status' => 'Completed',
            'completed_date' => $request->actual_date,
            'batch_number' => $request->batch_number,
            'dose_ml' => $request->dose_ml,
            'next_due_date' => $request->next_due_date,
        ]);

        // Auto-create next schedule if booster needed
        if ($request->next_due_date) {
            VaccinationSchedule::create([
                'animal_id' => $schedule->animal_id,
                'vaccine_name' => $schedule->vaccine_name,
                'disease_prevented' => $schedule->disease_prevented,
                'scheduled_date' => $request->next_due_date,
                'vet_id' => $vet->vet_id,
                'status' => 'Pending',
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Chanjo imekamilika',
            'data' => $schedule
        ]);
    }

    // =================================================================
    // FARMER: View my animals' vaccination history
    // =================================================================
    public function farmerHistory(Request $request)
    {
        $farmer = $request->user()->farmer;

        $history = VaccinationSchedule::forFarmer($farmer->farmer_id)
            ->with(['animal', 'veterinarian.user'])
            ->latest('completed_date')
            ->paginate(15);

        return response()->json([
            'status' => 'success',
            'data' => $history
        ]);
    }

    // =================================================================
    // VET: Upcoming reminders (dashboard)
    // =================================================================
    public function reminders(Request $request)
    {
        $vet = $request->user()->veterinarian;

        $today = VaccinationSchedule::today()->where('vet_id', $vet->vet_id)->count();
        $upcoming = VaccinationSchedule::upcoming(7)->where('vet_id', $vet->vet_id)->count();
        $missed = VaccinationSchedule::missed()->where('vet_id', $vet->vet_id)->count();

        return response()->json([
            'status' => 'success',
            'data' => [
                'today' => $today,
                'upcoming_7_days' => $upcoming,
                'missed' => $missed,
            ]
        ]);
    }

    // =================================================================
    // PDF: Vaccination Certificate
    // =================================================================
    public function certificate($schedule_id)
    {
        $user = request()->user();
        $schedule = VaccinationSchedule::where(function ($q) use ($user) {
            $q->whereHas('animal.farmer', fn($q) => $q->where('id', $user->farmer?->farmer_id))
              ->orWhere('vet_id', $user->veterinarian?->vet_id);
        })->with(['animal', 'veterinarian.user', 'animal.farmer.user'])->findOrFail($schedule_id);

        if ($schedule->status !== 'Completed') {
            return response()->json(['status' => 'error', 'message' => 'Chanjo bado haijakamilika'], 400);
        }

        $pdf = Pdf::loadView('pdf.vaccination-certificate', compact('schedule'))
            ->setPaper('a4', 'portrait')
            ->setOptions(['defaultFont' => 'DejaVu Sans']);

        $filename = "Cheti-Chanjo-{$schedule->animal->tag_number}-" .
            $schedule->completed_date->format('d-m-Y') . ".pdf";

        return $pdf->download($filename);
    }

    // =================================================================
    // BULK: Upload vaccination records (CSV)
    // =================================================================
    public function bulkUpload(Request $request)
    {
        $vet = $request->user()->veterinarian;

        $validator = Validator::make($request->all(), [
            'csv_file' => 'required|file|mimes:csv,txt|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $file = $request->file('csv_file');
        $handle = fopen($file, 'r');
        $header = fgetcsv($handle); // Skip header

        $inserted = 0;
        DB::beginTransaction();

        try {
            while ($row = fgetcsv($handle)) {
                if (count($row) < 5) continue;

                $tag = trim($row[0]);
                $vaccine = trim($row[1]);
                $disease = trim($row[2]);
                $date = Carbon::createFromFormat('d/m/Y', trim($row[3]))?->format('Y-m-d');
                $batch = trim($row[4] ?? '');

                $animal = Livestock::where('tag_number', $tag)->first();
                if (!$animal) continue;

                VaccinationSchedule::updateOrCreate(
                    [
                        'animal_id' => $animal->animal_id,
                        'vaccine_name' => $vaccine,
                        'completed_date' => $date,
                    ],
                    [
                        'vet_id' => $vet->vet_id,
                        'disease_prevented' => $disease,
                        'status' => 'Completed',
                        'batch_number' => $batch,
                    ]
                );

                $inserted++;
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => "$inserted rekodi za chanjo zimeingizwa"
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Hitilafu katika faili'], 500);
        }
    }
}
