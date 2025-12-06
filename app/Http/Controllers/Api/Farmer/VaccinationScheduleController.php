<?php

namespace App\Http\Controllers\Farmer;

use App\Http\Controllers\Controller;
use App\Models\VaccinationSchedule;
use Illuminate\Http\Request;

/**
 * Farmer Role: Read (Review) and limited Update (Execution)
 * The Farmer confirms the execution of the Vet's plan.
 */
class VaccinationScheduleController extends Controller
{
    // Farmer can read all plans for their farm
    public function index(Request $request)
    {
        $farmerId = $request->user()->farmer->id;

        $query = VaccinationSchedule::forFarmer($farmerId)
            ->with(['animal:id,tag_number,name', 'veterinarian:vet_id,name'])
            ->orderByDesc('scheduled_date');

        if ($request->boolean('overdue')) {
            $query->dueOrOverdue();
        }

        $schedules = $query->paginate(20);

        return response()->json(['status' => 'success', 'data' => $schedules]);
    }

    // Farmer can view a single treatment plan detail
    public function show($schedule_id)
    {
        $farmerId = auth()->user()->farmer->id;

        $schedule = VaccinationSchedule::where('schedule_id', $schedule_id)
            ->whereHas('animal.farmer', fn($q) => $q->where('id', $farmerId)) // Ensure ownership
            ->with(['animal', 'veterinarian', 'vetAction']) // Include link to history
            ->firstOrFail();

        return response()->json(['status' => 'success', 'data' => $schedule]);
    }

    /**
     * Farmer's ONLY write access: to mark a plan as executed/administered.
     */
    public function markCompleted(Request $request, $schedule_id)
    {
        $farmerId = $request->user()->farmer->id;

        // 1. Validate required execution fields
        $validated = $request->validate([
            // Batch number is mandatory for traceability upon execution
            'batch_number' => 'required|string|max:100',
            // Farmer confirms the date they administered the dose
            'completed_date' => 'nullable|date',
        ]);

        $schedule = VaccinationSchedule::where('schedule_id', $schedule_id)
            ->whereHas('animal.farmer', fn($q) => $q->where('id', $farmerId))
            ->whereIn('status', ['Pending', 'Missed']) // Only complete pending or missed items
            ->firstOrFail();

        // 2. Farmer updates execution details
        $schedule->update([
            'completed_date' => $validated['completed_date'] ? : now(),
            'batch_number' => $validated['batch_number'],
            'administered_by_user_id' => $request->user()->id,
            'status' => 'Completed',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Vaccination successfully marked as completed.',
            'data' => $schedule
        ]);
    }
}
