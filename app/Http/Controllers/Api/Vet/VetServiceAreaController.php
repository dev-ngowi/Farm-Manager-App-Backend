<?php

namespace App\Http\Controllers\Api\Farmer;

use App\Http\Controllers\Controller;
use App\Models\VetServiceArea;
use App\Models\Veterinarian;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class VetServiceAreaController extends Controller
{
    // =================================================================
    // INDEX: List all service areas for a vet
    // =================================================================
    public function index(Request $request, $vet_id)
    {
        $areas = VetServiceArea::where('vet_id', $vet_id)
            ->with(['region', 'district', 'ward'])
            ->active()
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $areas
        ]);
    }

    // =================================================================
    // STORE: Add new service area
    // =================================================================
    public function store(Request $request, $vet_id)
    {
        $user = $request->user();
        $vet = Veterinarian::where('user_id', $user->id)->first();

        if (!$vet || $vet->vet_id != $vet_id) {
            return response()->json(['status' => 'error', 'message' => 'Huna ruhusa'], 403);
        }

        $validator = Validator::make($request->all(), [
            'region_id' => 'required_without_all:district_id,ward_id|exists:regions,region_id',
            'district_id' => 'required_without:ward_id|exists:districts,district_id',
            'ward_id' => 'nullable|exists:wards,ward_id',
            'service_radius_km' => 'required|numeric|min:1|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $area = VetServiceArea::create([
            'vet_id' => $vet_id,
            'region_id' => $request->region_id,
            'district_id' => $request->district_id,
            'ward_id' => $request->ward_id,
            'service_radius_km' => $request->service_radius_km,
            'is_active' => true,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Eneo la huduma limeongezwa',
            'data' => $area->load(['region', 'district', 'ward'])
        ], 201);
    }

    // =================================================================
    // UPDATE: Edit service area
    // =================================================================
    public function update(Request $request, $vet_id, $area_id)
    {
        $user = $request->user();
        $vet = Veterinarian::where('user_id', $user->id)->first();
        if (!$vet || $vet->vet_id != $vet_id) {
            return response()->json(['status' => 'error', 'message' => 'Huna ruhusa'], 403);
        }

        $area = VetServiceArea::where('vet_id', $vet_id)->findOrFail($area_id);

        $validator = Validator::make($request->all(), [
            'service_radius_km' => 'sometimes|numeric|min:1|max:500',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $area->update($request->only(['service_radius_km', 'is_active']));

        return response()->json([
            'status' => 'success',
            'message' => 'Eneo limesasishwa',
            'data' => $area
        ]);
    }

    // =================================================================
    // DESTROY: Delete service area
    // =================================================================
    public function destroy(Request $request, $vet_id, $area_id)
    {
        $user = $request->user();
        $vet = Veterinarian::where('user_id', $user->id)->first();
        if (!$vet || $vet->vet_id != $vet_id) {
            return response()->json(['status' => 'error', 'message' => 'Huna ruhusa'], 403);
        }

        $area = VetServiceArea::where('vet_id', $vet_id)->findOrFail($area_id);
        $area->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Eneo limefutwa'
        ]);
    }

    // =================================================================
    // FIND VETS BY LOCATION (for farmers)
    // =================================================================
    public function findByLocation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ward_id' => 'sometimes|exists:wards,ward_id',
            'district_id' => 'sometimes|exists:districts,district_id',
            'region_id' => 'sometimes|exists:regions,region_id',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $query = VetServiceArea::active()
            ->with(['veterinarian.user', 'veterinarian.location']);

        if ($request->filled('ward_id')) {
            $query->coveringWard($request->ward_id);
        } elseif ($request->filled('district_id')) {
            $query->coveringDistrict($request->district_id);
        } elseif ($request->filled('region_id')) {
            $query->where('region_id', $request->region_id);
        }

        $areas = $query->get();

        $vets = $areas->map(fn($area) => $area->veterinarian)->unique('vet_id');

        return response()->json([
            'status' => 'success',
            'data' => $vets->values()
        ]);
    }
}
