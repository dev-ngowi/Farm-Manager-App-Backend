<?php

namespace App\Http\Controllers\Api\Farmer;

use App\Http\Controllers\Controller;
use App\Models\Lactation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LactationController extends Controller
{
    /**
     * Display a listing of the farmer's lactation records.
     * Includes filtering by status and dam.
     */
    public function index(Request $request)
    {
        // Scope query to lactations belonging to animals owned by the current farmer
        $query = Lactation::query()
            ->whereHas('dam.farmer', fn($q) => $q->where('farmer_id', $request->user()->farmer->id))
            // Eager load dam tag and name
            ->with(['dam' => fn($q) => $q->select('animal_id', 'tag_number', 'name')]);

        // Apply filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('dam_id')) {
            $query->where('dam_id', $request->dam_id);
        }

        // Use pagination for performance
        $lactations = $query
            ->latest('start_date')
            ->paginate($request->get('per_page', 15)); // Default to 15 items per page

        return response()->json([
            'status' => 'success',
            'data' => $lactations->items(), // Return only the paginated items
            'meta' => [ // Extract and return pagination metadata
                'total' => $lactations->total(),
                'per_page' => $lactations->perPage(),
                'current_page' => $lactations->currentPage(),
                'last_page' => $lactations->lastPage(),
            ]
        ]);
    }

    /**
     * Update the specified lactation record.
     */
    public function update(Request $request, $id)
    {
        // 1. Authorize: Ensure the lactation belongs to the current farmer
        $lactation = Lactation::whereHas('dam.farmer', fn($q) => $q->where('farmer_id', $request->user()->farmer->id))
            ->findOrFail($id);

        // 2. Validation with custom date checks against the existing start_date
        $validator = Validator::make($request->all(), [
            'peak_date' => [
                'nullable',
                'date',
                function ($attribute, $value, $fail) use ($lactation) {
                    // Check date against the model's start_date property
                    if (strtotime($value) < strtotime($lactation->start_date)) {
                        $fail("The $attribute must be a date after or equal to the start date ({$lactation->start_date}).");
                    }
                }
            ],
            'dry_off_date' => [
                'nullable',
                'date',
                function ($attribute, $value, $fail) use ($lactation) {
                    // Check date against the model's start_date property
                    if (strtotime($value) < strtotime($lactation->start_date)) {
                        $fail("The $attribute must be a date after or equal to the start date ({$lactation->start_date}).");
                    }
                }
            ],
            'total_milk_kg' => 'nullable|numeric|min:0',
            'days_in_milk' => 'nullable|integer|min:0',
            'status' => 'in:Ongoing,Completed',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        // 3. Update the record
        $lactation->update($request->only([
            'peak_date', 'dry_off_date', 'total_milk_kg', 'days_in_milk', 'status'
        ]));

        return response()->json([
            'status' => 'success',
            'message' => 'Lactation updated successfully.',
            'data' => $lactation->load('dam')
        ]);
    }
}
