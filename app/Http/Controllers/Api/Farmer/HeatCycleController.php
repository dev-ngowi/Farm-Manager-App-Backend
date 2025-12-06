<?php

namespace App\Http\Controllers\Api\Farmer;

use App\Http\Controllers\Controller;
use App\Models\HeatCycle;
use App\Models\Livestock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class HeatCycleController extends Controller
{
    /**
     * Get all heat cycles for the authenticated farmer
     */
    public function index(Request $request)
    {
        $query = HeatCycle::query()
            ->whereHas('dam.farmer', fn($q) => $q->where('farmer_id', $request->user()->farmer->id))
            ->with(['dam' => fn($q) => $q->select('animal_id', 'tag_number', 'name', 'species_id')])
            ->select('id', 'dam_id', 'observed_date', 'intensity', 'inseminated', 'next_expected_date', 'notes');

        if ($request->filled('dam_id')) {
            $query->where('dam_id', $request->dam_id);
        }
        if ($request->boolean('not_inseminated')) {
            $query->notInseminated();
        }
        if ($request->boolean('expected_soon')) {
            $query->expectedSoon();
        }

        $heatCycles = $query->latest('observed_date')->get();

        return response()->json([
            'status' => 'success',
            'data' => $heatCycles,
            'meta' => [
                'total' => $heatCycles->count(),
                'active' => $heatCycles->where('is_current', true)->count(),
                'due_soon' => $heatCycles->where('next_expected_date', '<=', now()->addDays(7))->count(),
            ]
        ]);
    }

    /**
     * Get a single heat cycle by ID
     */
    public function show(Request $request, $id)
    {
        try {
            // Load heat cycle with dam and breed information
            $heatCycle = HeatCycle::query()
                ->whereHas('dam.farmer', fn($q) => $q->where('farmer_id', $request->user()->farmer->id))
                ->with([
                    'dam' => function($q) {
                        $q->select('animal_id', 'tag_number', 'name', 'species_id', 'breed_id', 'date_of_birth')
                          ->with(['breed' => fn($b) => $b->select('id', 'breed_name')]);
                    }
                ])
                ->select('id', 'dam_id', 'observed_date', 'intensity', 'inseminated', 'next_expected_date', 'notes', 'created_at', 'updated_at')
                ->findOrFail($id);

            // Calculate additional metadata
            $observedDate = Carbon::parse($heatCycle->observed_date);
            $daysSinceObserved = $observedDate->diffInDays(now());

            $daysUntilNext = null;
            if ($heatCycle->next_expected_date) {
                $nextExpectedDate = Carbon::parse($heatCycle->next_expected_date);
                $daysUntilNext = now()->diffInDays($nextExpectedDate, false);
            }

            $isActive = false;
            if (!$heatCycle->inseminated && $heatCycle->next_expected_date) {
                $isActive = Carbon::parse($heatCycle->next_expected_date)->isFuture();
            }

            $metadata = [
                'is_active' => $isActive,
                'days_since_observed' => $daysSinceObserved,
                'days_until_next' => $daysUntilNext,
                'status' => $this->calculateStatus($heatCycle),
            ];

            $response = $heatCycle->toArray();
            $response['metadata'] = $metadata;

            // Transform breed_name to name for consistency with frontend
            if (isset($response['dam']['breed']['breed_name'])) {
                $response['dam']['breed']['name'] = $response['dam']['breed']['breed_name'];
            }

            return response()->json([
                'status' => 'success',
                'data' => $response
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching heat cycle details', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Could not load heat cycle details.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new heat cycle record
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'dam_id' => 'required|exists:livestock,animal_id',
            'observed_date' => 'required|date|before_or_equal:today',
            'intensity' => 'required|in:Weak,Moderate,Strong,Standing Heat',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $dam = Livestock::where('animal_id', $request->dam_id)
            ->where('farmer_id', $request->user()->farmer->id)
            ->firstOrFail();

        $heatCycle = HeatCycle::create([
            'dam_id' => $dam->animal_id,
            'observed_date' => $request->observed_date,
            'intensity' => $request->intensity,
            'notes' => $request->notes,
            'next_expected_date' => Carbon::parse($request->observed_date)->addDays(21),
            'inseminated' => false,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Heat cycle recorded successfully',
            'data' => $heatCycle->load('dam')
        ], 201);
    }

    /**
     * Update an existing heat cycle
     */
    public function update(Request $request, $id)
    {
        $heatCycle = HeatCycle::whereHas('dam.farmer', fn($q) => $q->where('farmer_id', $request->user()->farmer->id))
            ->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'observed_date' => 'date|before_or_equal:today',
            'intensity' => 'in:Weak,Moderate,Strong,Standing Heat',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $heatCycle->update($request->only(['observed_date', 'intensity', 'notes']));

        // Recalculate next expected date if observed_date changed
        if ($request->filled('observed_date')) {
            $heatCycle->next_expected_date = Carbon::parse($request->observed_date)->addDays(21);
            $heatCycle->save();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Heat cycle updated successfully',
            'data' => $heatCycle->load('dam')
        ]);
    }

    /**
     * Delete a heat cycle
     */
    public function destroy(Request $request, $id)
    {
        $heatCycle = HeatCycle::whereHas('dam.farmer', fn($q) => $q->where('farmer_id', $request->user()->farmer->id))
            ->findOrFail($id);

        $heatCycle->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Heat cycle deleted successfully'
        ]);
    }

    /**
     * Helper method to calculate heat cycle status
     */
    private function calculateStatus(HeatCycle $heatCycle): string
    {
        if ($heatCycle->inseminated) {
            return 'Inseminated';
        }

        if ($heatCycle->next_expected_date && Carbon::parse($heatCycle->next_expected_date)->isFuture()) {
            return 'Active';
        }

        return 'Completed';
    }
}
