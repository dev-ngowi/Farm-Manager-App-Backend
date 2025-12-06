<?php

namespace App\Http\Controllers\Vet;

use App\Http\Controllers\Controller;
use App\Models\VaccinationSchedule;
use App\Models\Livestock; // Assuming Livestock model
use Illuminate\Http\Request;

/**
 * Vet Role: Create, Read, Update, Delete (Planning & Prescription)
 * The Vet dictates when and what vaccine must be given.
 */
class VaccinationScheduleController extends Controller
{
    // Vet can read all plans they created or that belong to their clients
    public function index(Request $request)
    {
        $vetId = $request->user()->vet->id;

        $schedules = VaccinationSchedule::where('vet_id', $vetId)
            ->with(['animal:id,tag_number,name', 'animal.farmer'])
            ->orderByDesc('scheduled_date')
            ->paginate(20);

        return response()->json(['status' => 'success', 'data' => $schedules]);
    }

    // Vet can create a new plan (Prescription)
    public function store(Request $request)
    {
        $validated = $request->validate([
            'animal_id' => 'required|exists:livestock,id',
            'vaccine_name' => 'required|string|max:255',
            'disease_prevented' => 'required|string|max:255',
            'scheduled_date' => 'required|date|after_or_equal:today',
            'notes' => 'nullable|string',
            // Batch number can be set by the Vet if they are planning to supply the vaccine
            'batch_number' => 'nullable|string|max:100',
            // Optional link to the VetAction that prompted this schedule
            'vet_action_id' => 'nullable|exists:vet_actions,action_id',
        ]);

        $schedule = VaccinationSchedule::create([
            ...$validated,
            'vet_id' => $request->user()->vet->id,
            'status' => 'Pending',
        ]);

        return response()->json(['status' => 'success', 'message' => 'Vaccination schedule planned successfully.', 'data' => $schedule], 201);
    }

    // ... (Add update and destroy methods here to allow the Vet to modify the plan)
}
