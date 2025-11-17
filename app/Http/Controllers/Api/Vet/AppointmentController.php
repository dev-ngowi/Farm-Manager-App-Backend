<?php

namespace App\Http\Controllers\Api\Vet;

use App\Http\Controllers\Controller;
use App\Models\VetAppointment;
use App\Models\Veterinarian;
use App\Models\Farmer;
use App\Models\Livestock;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class AppointmentController extends Controller
{
    // =================================================================
    // FARMER: Schedule new appointment
    // =================================================================
    public function store(Request $request)
    {
        $farmer = $request->user()->farmer;

        $validator = Validator::make($request->all(), [
            'vet_id' => 'required|exists:veterinarians,vet_id',
            'animal_id' => 'nullable|exists:livestock,animal_id',
            'health_id' => 'nullable|exists:health_reports,health_id',
            'appointment_type' => 'required|in:Emergency,Routine Checkup,Vaccination,Surgery,Follow-up,Consultation',
            'appointment_date' => 'required|date|after_or_equal:today',
            'appointment_time' => 'required|date_format:H:i',
            'location_type' => 'required|in:Clinic Visit,Farm Visit',
            'farm_location_id' => 'required_if:location_type,Farm Visit|exists:locations,location_id',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        // Validate animal belongs to farmer
        if ($request->animal_id) {
            Livestock::where('animal_id', $request->animal_id)
                ->where('farmer_id', $farmer->farmer_id)
                ->firstOrFail();
        }

        // Validate health report belongs to farmer
        if ($request->health_id) {
            \App\Models\HealthReport::where('health_id', $request->health_id)
                ->where('farmer_id', $farmer->farmer_id)
                ->firstOrFail();
        }

        // Check for conflicts
        $conflict = VetAppointment::where('vet_id', $request->vet_id)
            ->where('appointment_date', $request->appointment_date)
            ->where('appointment_time', $request->appointment_time)
            ->whereIn('status', ['Scheduled', 'Confirmed', 'In Progress'])
            ->exists();

        if ($conflict) {
            return response()->json([
                'status' => 'error',
                'message' => 'Wakati huu tayari umechukuliwa. Tafadhali chagua wakati mwingine.'
            ], 409);
        }

        $appointment = VetAppointment::create([
            'farmer_id' => $farmer->farmer_id,
            'vet_id' => $request->vet_id,
            'animal_id' => $request->animal_id,
            'health_id' => $request->health_id,
            'appointment_type' => $request->appointment_type,
            'appointment_date' => $request->appointment_date,
            'appointment_time' => $request->appointment_date . ' ' . $request->appointment_time,
            'location_type' => $request->location_type,
            'farm_location_id' => $request->location_type === 'Farm Visit' ? $request->farm_location_id : null,
            'status' => 'Scheduled',
            'estimated_duration_minutes' => $this->estimateDuration($request->appointment_type),
            'notes' => $request->notes,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Ombi lako la miadi limepokewa. Daktari atathibitisha hivi karibuni.',
            'data' => $appointment->load(['veterinarian.user', 'animal', 'location'])
        ], 201);
    }

    // =================================================================
    // VET: List appointments (calendar view)
    // =================================================================
    public function vetCalendar(Request $request)
    {
        $vet = $request->user()->veterinarian;

        $start = $request->get('start', now()->startOfWeek());
        $end = $request->get('end', now()->endOfWeek());

        $appointments = VetAppointment::forVet($vet->vet_id)
            ->whereBetween('appointment_date', [$start, $end])
            ->with(['farmer.user', 'animal'])
            ->get()
            ->map(function ($appt) {
                return [
                    'id' => $appt->appointment_id,
                    'title' => $appt->type_swahili . ' - ' . ($appt->animal?->tag_number ?? 'Hapana Mnyama'),
                    'start' => $appt->appointment_date->format('Y-m-d') . 'T' . $appt->appointment_time->format('H:i'),
                    'end' => $appt->appointment_date->format('Y-m-d') . 'T' . $appt->appointment_time->addMinutes($appt->estimated_duration_minutes)->format('H:i'),
                    'status' => $appt->status_swahili,
                    'color' => $this->getColorByType($appt->appointment_type),
                    'farmer' => $appt->farmer->user->fullname,
                    'location' => $appt->location_text,
                    'editable' => in_array($appt->status, ['Scheduled', 'Confirmed']),
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => $appointments
        ]);
    }

    // =================================================================
    // VET: Confirm / Reschedule / Cancel
    // =================================================================
    public function vetRespond(Request $request, $appointment_id)
    {
        $vet = $request->user()->veterinarian;

        $appointment = VetAppointment::forVet($vet->vet_id)->findOrFail($appointment_id);

        $validator = Validator::make($request->all(), [
            'action' => 'required|in:confirm,reschedule,cancel',
            'new_date' => 'required_if:action,reschedule|date|after_or_equal:today',
            'new_time' => 'required_if:action,reschedule|date_format:H:i',
            'cancellation_reason' => 'required_if:action,cancel|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        if ($request->action === 'confirm') {
            $appointment->update(['status' => 'Confirmed']);
            $message = 'Miadi imethibitishwa';
        }

        if ($request->action === 'reschedule') {
            $conflict = VetAppointment::forVet($vet->vet_id)
                ->where('appointment_date', $request->new_date)
                ->where('appointment_time', $request->new_date . ' ' . $request->new_time)
                ->where('appointment_id', '!=', $appointment_id)
                ->exists();

            if ($conflict) {
                return response()->json(['status' => 'error', 'message' => 'Wakati mpya tayari umechukuliwa'], 409);
            }

            $appointment->update([
                'appointment_date' => $request->new_date,
                'appointment_time' => $request->new_date . ' ' . $request->new_time,
                'status' => 'Scheduled',
            ]);
            $message = 'Miadi imepangwa upya';
        }

        if ($request->action === 'cancel') {
            $appointment->update([
                'status' => 'Cancelled',
                'cancellation_reason' => $request->cancellation_reason,
            ]);
            $message = 'Miadi imeghairiwa';
        }

        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $appointment
        ]);
    }

    // =================================================================
    // VET: Start / End appointment (check-in/out)
    // =================================================================
    public function vetCheckInOut(Request $request, $appointment_id)
    {
        $vet = $request->user()->veterinarian;
        $appointment = VetAppointment::forVet($vet->vet_id)->findOrFail($appointment_id);

        if (!in_array($appointment->status, ['Confirmed', 'In Progress'])) {
            return response()->json(['status' => 'error', 'message' => 'Miadi haiko tayari'], 400);
        }

        if ($request->action === 'start') {
            $appointment->update([
                'status' => 'In Progress',
                'actual_start_time' => now(),
            ]);
            $msg = 'Miadi imeanza';
        } else {
            $appointment->update([
                'status' => 'Completed',
                'actual_end_time' => now(),
            ]);
            $msg = 'Miadi imekamilika';
        }

        return response()->json(['status' => 'success', 'message' => $msg]);
    }

    // =================================================================
    // FARMER: View my appointments
    // =================================================================
    public function farmerAppointments(Request $request)
    {
        $farmer = $request->user()->farmer;

        $appointments = VetAppointment::forFarmer($farmer->farmer_id)
            ->with(['veterinarian.user', 'animal', 'location'])
            ->latest('appointment_date')
            ->paginate(10);

        return response()->json([
            'status' => 'success',
            'data' => $appointments
        ]);
    }

    // =================================================================
    // PUBLIC: Vet availability (for booking form)
    // =================================================================
    public function vetAvailability(Request $request, $vet_id)
    {
        $vet = Veterinarian::findOrFail($vet_id);

        $date = $request->get('date', now()->format('Y-m-d'));
        $slots = [];

        $start = Carbon::parse("$date 08:00");
        $end = Carbon::parse("$date 17:00");

        while ($start < $end) {
            $time = $start->format('H:i');
            $booked = VetAppointment::forVet($vet->vet_id)
                ->where('appointment_date', $date)
                ->where('appointment_time', 'LIKE', "%$time%")
                ->whereIn('status', ['Scheduled', 'Confirmed', 'In Progress'])
                ->exists();

            $slots[] = [
                'time' => $time,
                'available' => !$booked,
                'label' => $booked ? 'Tayari' : 'Inapatikana',
            ];

            $start->addMinutes(30);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'vet' => $vet->only('vet_id', 'clinic_name'),
                'date' => $date,
                'slots' => $slots,
            ]
        ]);
    }

    // =================================================================
    // PDF: Appointment Receipt
    // =================================================================
    public function downloadPdf($appointment_id)
    {
        $user = request()->user();
        $appointment = VetAppointment::where(function ($q) use ($user) {
            $q->where('farmer_id', $user->farmer?->farmer_id)
              ->orWhereHas('veterinarian', fn($q) => $q->where('user_id', $user->id));
        })->with(['farmer.user', 'veterinarian.user', 'animal', 'location'])->findOrFail($appointment_id);

        $pdf = Pdf::loadView('pdf.appointment-receipt', compact('appointment'))
            ->setPaper('a4', 'portrait')
            ->setOptions(['defaultFont' => 'DejaVu Sans']);

        $filename = "Risiti-Miadi-{$appointment->type_swahili}-" .
            $appointment->appointment_date->format('d-m-Y') . ".pdf";

        return $pdf->download($filename);
    }

    // =================================================================
    // HELPER: Estimate duration
    // =================================================================
    private function estimateDuration($type)
    {
        return match ($type) {
            'Emergency' => 90,
            'Surgery' => 120,
            'Vaccination' => 30,
            'Routine Checkup' => 45,
            'Follow-up' => 30,
            'Consultation' => 20,
            default => 60
        };
    }

    // =================================================================
    // HELPER: Color by type
    // =================================================================
    private function getColorByType($type)
    {
        return match ($type) {
            'Emergency' => '#EF4444',
            'Surgery' => '#F59E0B',
            'Vaccination' => '#10B981',
            'Routine Checkup' => '#3B82F6',
            default => '#6B7280'
        };
    }
}
