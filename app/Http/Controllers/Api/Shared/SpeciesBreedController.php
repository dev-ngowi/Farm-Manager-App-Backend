<?php

namespace App\Http\Controllers\Api\Shared;

use App\Http\Controllers\Controller;
use App\Models\Species;
use App\Models\Breed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class SpeciesBreedController extends Controller
{
    // =================================================================
    // SPECIES CRUD
    // =================================================================

    // In SpeciesBreedController.php

    public function speciesIndex()
    {
        $species = Species::withCount('livestock as total_animals')
            ->with(['breeds' => function ($query) {
                $query->withCount('livestock as livestock_count')
                    ->orderByDesc('livestock_count');
            }])
            ->orderBy('species_name')
            ->get()
            ->map(function ($specie) {
                $specie->top_breed = $specie->breeds->first();
                unset($specie->breeds); // optional: clean up
                return $specie;
            });

        return response()->json([
            'status' => 'success',
            'data' => $species
        ], 200);
    }

    public function speciesStore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'species_name' => 'required|string|max:100|unique:species',
            'description' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $species = Species::create($request->only(['species_name', 'description']));

        return response()->json([
            'status' => 'success',
            'message' => 'Species created successfully',
            'data' => $species
        ], 201);
    }

    public function speciesShow($id)
    {
        $species = Species::with([
            'breeds' => fn($q) => $q->withCount('livestock')->orderByDesc('livestock_count'),
            'livestock' => fn($q) => $q->take(5)->latest()
        ])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $species
        ], 200);
    }

    public function speciesUpdate(Request $request, $id)
    {
        $species = Species::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'species_name' => 'required|string|max:100|unique:species,species_name,' . $id . ',species_id',
            'description' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $species->update($request->only(['species_name', 'description']));

        return response()->json([
            'status' => 'success',
            'message' => 'Species updated',
            'data' => $species
        ], 200);
    }

    public function speciesDestroy($id)
    {
        $species = Species::findOrFail($id);

        if ($species->livestock()->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot delete species with existing animals'
            ], 400);
        }

        $species->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Species deleted'
        ], 200);
    }

    // =================================================================
    // BREED CRUD
    // =================================================================

    public function breedsIndex(Request $request)
    {
        $query = Breed::with(['species', 'livestock'])
            ->withCount('livestock as total_animals');

        if ($request->species_id) {
            $query->where('species_id', $request->species_id);
        }

        if ($request->purpose) {
            $query->where('purpose', 'like', "%{$request->purpose}%");
        }

        $breeds = $query->orderBy('breed_name')->get();

        return response()->json([
            'status' => 'success',
            'data' => $breeds
        ], 200);
    }

    public function breedsStore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'species_id' => 'required|exists:species,species_id',
            'breed_name' => 'required|string|max:100|unique:breeds',
            'origin' => 'nullable|string|max:100',
            'purpose' => 'required|in:Meat,Milk,Wool,Dual-purpose,Other',
            'average_weight_kg' => 'nullable|numeric|min:0',
            'maturity_months' => 'nullable|integer|min:1|max:120'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $breed = Breed::create($request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'Breed created',
            'data' => $breed->load('species')
        ], 201);
    }

    public function breedsShow($id)
    {
        $breed = Breed::with([
            'species',
            'livestock' => fn($q) => $q->with('farmer')->latest()->take(10),
            'milkYields' => fn($q) => $q->where('yield_date', '>=', now()->subDays(30))
        ])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $breed
        ], 200);
    }

    public function breedsUpdate(Request $request, $id)
    {
        $breed = Breed::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'species_id' => 'required|exists:species,species_id',
            'breed_name' => 'required|string|max:100|unique:breeds,breed_name,' . $id . ',id',
            'origin' => 'nullable|string|max:100',
            'purpose' => 'required|in:Meat,Milk,Wool,Dual-purpose,Other',
            'average_weight_kg' => 'nullable|numeric',
            'maturity_months' => 'nullable|integer|min:1|max:120'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $breed->update($request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'Breed updated',
            'data' => $breed->load('species')
        ], 200);
    }

    public function breedsDestroy($id)
    {
        $breed = Breed::findOrFail($id);

        if ($breed->livestock()->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot delete breed with registered animals'
            ], 400);
        }

        $breed->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Breed deleted'
        ], 200);
    }

    // =================================================================
    // EXTRA: Get breeds by species (for dropdowns)
    // =================================================================
    public function breedsBySpecies($species_id)
    {
        $breeds = Breed::where('species_id', $species_id)
            ->select('id', 'breed_name', 'purpose')
            ->orderBy('breed_name')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $breeds
        ], 200);
    }
}
