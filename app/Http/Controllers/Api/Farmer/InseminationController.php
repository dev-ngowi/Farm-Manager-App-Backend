<?php

namespace App\Http\Controllers\Api\Farmer;

use App\Http\Controllers\Controller;
use App\Models\Insemination;
use App\Models\Livestock;
use App\Models\Semen;
use App\Models\HeatCycle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class InseminationController extends Controller
{
    public function index(Request $request)
    {
        $query = Insemination::query()
            ->whereHas('dam.farmer', fn($q) => $q->where('farmer_id', $request->user()->farmer->id))
            ->with([
                'dam' => fn($q) => $q->select('animal_id', 'tag_number', 'name', 'species_id'),
                'sire' => fn($q) => $q->select('animal_id', 'tag_number', 'name'),
                'semen' => fn($q) => $q->select('id', 'straw_code', 'bull_name'),
                'delivery'
            ]);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('breeding_method')) {
            $query->where('breeding_method', $request->breeding_method);
        }
        if ($request->boolean('pregnant')) {
            $query->whereHas('pregnancyChecks', fn($q) => $q->where('result', 'Pregnant'));
        }
        if ($request->boolean('due_soon')) {
            $query->whereBetween('expected_delivery_date', [now(), now()->addDays(14)]);
        }

        $inseminations = $query->latest('insemination_date')->get();

        return response()->json([
            'status' => 'success',
            'data' => $inseminations,
            'meta' => [
                'total' => $inseminations->count(),
                'pregnant' => $inseminations->where('is_pregnant', true)->count(),
                'due_soon' => $inseminations->where('days_to_due', '<=', 14)->where('days_to_due', '>', 0)->count(),
            ]
        ]);
    }

    /**
     * Display the specified insemination record.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, $id)
    {
        $insemination = Insemination::query()
            // Ensure the insemination belongs to the authenticated farmer's livestock
            ->whereHas('dam.farmer', fn($q) => $q->where('farmer_id', $request->user()->farmer->id))
            ->with([
                'dam' => fn($q) => $q->select('animal_id', 'tag_number', 'name', 'species_id', 'farmer_id'),
                'sire' => fn($q) => $q->select('animal_id', 'tag_number', 'name'),
                'semen' => fn($q) => $q->select('id', 'straw_code', 'bull_name'),
                'heatCycle', // Include the associated heat cycle
                'technician' => fn($q) => $q->select('id', 'name'), // Assuming 'technician' is a User relationship
                'delivery', // Include delivery details if available
                'pregnancyChecks' // Include all related pregnancy checks
            ])
            ->findOrFail($id); // Find the record or throw a 404

        return response()->json([
            'status' => 'success',
            'data' => $insemination,
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'dam_id' => 'required|exists:livestock,animal_id',
            'sire_id' => 'required_if:breeding_method,Natural|nullable|exists:livestock,animal_id',
            'semen_id' => 'required_if:breeding_method,AI|nullable|exists:semen_inventory,id',
            'breeding_method' => 'required|in:Natural,AI',
            'heat_cycle_id' => 'required|exists:heat_cycles,id',
            'insemination_date' => 'required|date|before_or_equal:today',
            'technician_id' => 'nullable|exists:users,id',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $dam = Livestock::where('animal_id', $request->dam_id)
            ->where('farmer_id', $request->user()->farmer->id)
            ->firstOrFail();

        $heatCycle = HeatCycle::where('id', $request->heat_cycle_id)
            ->where('dam_id', $dam->animal_id)
            ->firstOrFail();

        // Calculate gestation period based on species
        $gestationDays = match ($dam->species->species_name) {
            'Cattle' => 283, // ~9 months
            'Goat' => 150,   // ~5 months
            'Sheep' => 147,  // ~5 months
            default => 280,
        };

        $insemination = Insemination::create([
            'dam_id' => $dam->animal_id,
            'sire_id' => $request->sire_id,
            'semen_id' => $request->semen_id,
            'heat_cycle_id' => $heatCycle->id,
            'technician_id' => $request->technician_id,
            'breeding_method' => $request->breeding_method,
            'insemination_date' => $request->insemination_date,
            'expected_delivery_date' => Carbon::parse($request->insemination_date)->addDays($gestationDays),
            'status' => 'Pending',
            'notes' => $request->notes,
        ]);

        // Mark heat cycle as inseminated
        $heatCycle->update(['inseminated' => true]);

        // Mark semen as used if AI
        if ($request->breeding_method === 'AI') {
            Semen::where('id', $request->semen_id)->update(['used' => true]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Insemination recorded',
            'data' => $insemination->load(['dam', 'sire', 'semen'])
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $insemination = Insemination::whereHas('dam.farmer', fn($q) => $q->where('farmer_id', $request->user()->farmer->id))
            ->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'sire_id' => 'nullable|exists:livestock,animal_id',
            'semen_id' => 'nullable|exists:semen_inventory,id',
            'insemination_date' => 'date|before_or_equal:today',
            'expected_delivery_date' => 'date|after:insemination_date',
            'status' => 'in:Pending,Confirmed Pregnant,Not Pregnant,Delivered,Failed',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $insemination->update($request->only([
            'sire_id', 'semen_id', 'insemination_date',
            'expected_delivery_date', 'status', 'notes'
        ]));

        return response()->json([
            'status' => 'success',
            'message' => 'Insemination updated',
            'data' => $insemination->load(['dam', 'sire', 'semen'])
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $insemination = Insemination::whereHas('dam.farmer', fn($q) => $q->where('farmer_id', $request->user()->farmer->id))
            ->findOrFail($id);

        if ($insemination->delivery()->exists()) {
            return response()->json(['status' => 'error', 'message' => 'Cannot delete insemination with delivery'], 400);
        }

        $insemination->delete();

        return response()->json(['status' => 'success', 'message' => 'Insemination deleted']);
    }
}
