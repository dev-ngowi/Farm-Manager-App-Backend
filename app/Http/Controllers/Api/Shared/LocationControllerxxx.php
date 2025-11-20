<?php

namespace App\Http\Controllers\Api\Shared;

use App\Http\Controllers\Controller;
use App\Models\Region;
use App\Models\District;
use App\Models\Ward;
use App\Models\Location;
use App\Models\UserLocation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class LocationController extends Controller
{

    // =================================================================
    // GENERAL LOCATIONS CRUD (Address + GPS)
    // =================================================================

    public function indexLocations(Request $request)
    {


        try {
            $query = Location::withAllRelations()
                ->when($request->search, function ($q, $search) {
                    $q->where('address_details', 'LIKE', "%{$search}%")
                      ->orWhereHas('street', fn($sq) => $sq->where('street_name', 'LIKE', "%{$search}%"))
                      ->orWhereHas('ward', fn($wq) => $wq->where('ward_name', 'LIKE', "%{$search}%"));
                })
                ->when($request->region_id, fn($q) => $q->inRegion($request->region_id))
                ->when($request->district_id, fn($q) => $q->inDistrict($request->district_id))
                ->when($request->ward_id, fn($q) => $q->inWard($request->ward_id))
                ->when($request->has_gps, fn($q) => $q->hasCoordinates());

            $locations = $query->latest()->paginate($request->per_page ?? 20);

            return response()->json([
                'status' => 'success',
                'data' => $locations->items(),
                'meta' => [
                    'current_page' => $locations->currentPage(),
                    'total' => $locations->total(),
                    'per_page' => $locations->perPage(),
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch locations.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function storeLocation(Request $request)
    {
        $userId = Auth::id();

        // --- START LOGGING ---
        Log::info('Attempting to store new Location.', [
            'user_id' => $userId,
            'payload' => $request->all(), // Log the incoming data payload
            'ip' => $request->ip(),
        ]);
        // --- END LOGGING ---

        try {
            $validated = $request->validate([
                'region_id'       => 'required|exists:regions,id',
                'district_id'     => 'required|exists:districts,id',
                'ward_id'         => 'required|exists:wards,id',
                'street_id'       => 'nullable|exists:streets,id',
                'latitude'        => 'required_with:longitude|numeric|between:-90,90',
                'longitude'       => 'required_with:latitude|numeric|between:-180,180',
                'address_details' => 'nullable|string|max:500',
            ]);

            // --- LOG VALIDATED DATA ---
            Log::info('Location validation successful.', [
                'validated_data' => $validated,
            ]);
            // --- END LOGGING ---

            $location = DB::transaction(function () use ($validated) {
                return Location::create($validated);
            });

            // --- LOG LOCATION CREATION ---
            Log::info('Location record created.', [
                'location_id' => $location->id,
                'region_id' => $location->region_id,
            ]);
            // --- END LOGGING ---

            // ⭐ FIX: REMOVE AUTOMATIC USER LOCATION ASSIGNMENT
            // The client will handle this explicitly with the assignUserLocation endpoint.
            /*
            $autoAssignUserLoc = UserLocation::create([
                'user_id' => $userId,
                'location_id' => $location->id,
            ]);

            // --- LOG USER LOCATION ASSIGNMENT ---
            Log::info('User assigned to location.', [
                'user_location_id' => $autoAssignUserLoc->id,
            ]);
            // --- END LOGGING ---
            */

            return response()->json([
                'status' => 'success',
                'message' => 'Location created successfully.',
                // Ensure to still return the location data, now without the 'users' relation
                // if it's unnecessary at this step, but loading the location itself is fine.
                'data' => $location->load(['region', 'district', 'ward', 'street'])
            ], 201);

        } catch (ValidationException $e) {
            // ... (Error handling remains the same)
            Log::warning('Location creation validation failed.', [
                'user_id' => $userId,
                'errors' => $e->errors(),
                'payload' => $request->all(),
            ]);
            // --- END LOGGING ---

            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed.',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            // ... (Error handling remains the same)
            Log::error('Failed to create location due to a server error.', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => $request->all(),
            ]);
            // --- END LOGGING ---

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create location.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function showLocation($id)
    {
        try {
            $location = Location::withAllRelations()->findOrFail($id);

            return response()->json([
                'status' => 'success',
                'data' => $location
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Location not found.'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Something went wrong.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // =================================================================
    // USER LOCATION ASSIGNMENT (Many-to-Many + Primary)
    // =================================================================

    public function indexUserLocations(Request $request)
    {
        try {
            $userId = $request->user_id ?? $request->user()->id;

            $locations = UserLocation::with(['location.region', 'location.district', 'location.ward', 'location.street'])
                ->where('user_id', $userId)
                ->orderByDesc('is_primary')
                ->orderByDesc('created_at')
                ->paginate($request->per_page ?? 10);

            return response()->json([
                'status' => 'success',
                'data' => $locations->items(),
                'meta' => [
                    'current_page' => $locations->currentPage(),
                    'total' => $locations->total(),
                    'per_page' => $locations->perPage(),
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch user locations.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function assignUserLocation(Request $request)
    {
        $user = $request->user(); // Current authenticated user

        // --- START LOGGING ---
        Log::info('Attempting to assign User Location.', [
            'user_id' => $user->id,
            'payload' => $request->all(),
            'ip' => $request->ip(),
        ]);
        // --- END LOGGING ---

        try {
            $validated = $request->validate([
                'location_id' => 'required|exists:locations,id',
                'is_primary'  => 'sometimes|boolean',
            ]);

            $exists = UserLocation::where('user_id', $user->id)
                                ->where('location_id', $validated['location_id'])
                                ->exists();

            if ($exists) {
                // --- LOG CONFLICT ERROR ---
                Log::warning('User location assignment conflict (409). Location already assigned.', [
                    'user_id' => $user->id,
                    'location_id' => $validated['location_id'],
                ]);
                // --- END LOGGING ---

                return response()->json([
                    'status' => 'error',
                    'message' => 'This location is already assigned to the user.'
                ], 409);
            }

            $userLocation = DB::transaction(function () use ($user, $validated) {
                return UserLocation::create([
                    'user_id' => $user->id,
                    'location_id' => $validated['location_id'],
                    'is_primary' => $validated['is_primary'] ?? false,
                ]);
            });

            // --- LOG SUCCESS ---
            Log::info('Location assigned successfully to user.', [
                'user_id' => $user->id,
                'location_id' => $userLocation->location_id,
            ]);
            // --- END LOGGING ---

            return response()->json([
                'status' => 'success',
                'message' => 'Location assigned successfully.',
                'data' => $userLocation->load('location')
            ], 201);

        } catch (ValidationException $e) {
            // --- LOG VALIDATION ERROR ---
            Log::warning('User location assignment validation failed.', ['user_id' => $user->id, 'errors' => $e->errors()]);
            // --- END LOGGING ---

            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            // --- LOG GENERAL EXCEPTION ---
            Log::error('Failed to assign location due to a server error.', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            // --- END LOGGING ---

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to assign location.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function setPrimaryLocation(Request $request, $userLocationId)
    {
        try {
            $user = $request->user();

            $userLocation = UserLocation::where('user_id', $user->id)
                                        ->where('id', $userLocationId)
                                        ->firstOrFail();

            $userLocation->update(['is_primary' => true]);

            return response()->json([
                'status' => 'success',
                'message' => 'Primary location updated.',
                'data' => $userLocation->load('location')
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'User location not found.'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update primary location.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function removeUserLocation($userLocationId)
    {
        try {
            $user = request()->user();

            $userLocation = UserLocation::where('user_id', $user->id)
                                        ->where('id', $userLocationId)
                                        ->firstOrFail();

            $userLocation->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Location removed from user.'
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'User location not found.'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to remove location.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    // =================================================================
    // REGIONS CRUD
    // =================================================================

    public function indexRegions(Request $request)
    {
        try {
            $regions = Region::withCount('districts')
                ->when($request->search, fn($q) => $q->search($request->search))
                ->orderBy('region_name')
                ->paginate($request->per_page ?? 20);

            return response()->json([
                'status' => 'success',
                'data' => $regions->items(),
                'meta' => [
                    'current_page' => $regions->currentPage(),
                    'total' => $regions->total(),
                    'per_page' => $regions->perPage(),
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch regions.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // =================================================================
    // DISTRICTS CRUD
    // =================================================================

    public function indexDistricts(Request $request)
    {
        try {
            $query = District::with(['region'])
                ->when($request->search, fn($q) => $q->search($request->search))
                ->when($request->region_id, fn($q) => $q->inRegion($request->region_id));

            $districts = $query->orderBy('district_name')
                ->paginate($request->per_page ?? 20);

            return response()->json([
                'status' => 'success',
                'data' => $districts->items(),
                'meta' => [
                    'current_page' => $districts->currentPage(),
                    'total' => $districts->total(),
                    'per_page' => $districts->perPage(),
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch districts.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function storeDistrict(Request $request)
    {
        try {
            $validated = $request->validate([
                'region_id' => 'required|exists:regions,id',
                'district_name' => 'required|string|max:100|unique:districts,district_name,NULL,id,region_id,' . $request->region_id,
                'district_code' => 'nullable|string|max:10|unique:districts,district_code',
            ]);

            $district = DB::transaction(function () use ($validated) {
                return District::create($validated);
            });

            return response()->json([
                'status' => 'success',
                'message' => 'District created successfully.',
                'data' => $district->load('region')
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create district.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // =================================================================
    // WARDS CRUD - Already Perfect
    // =================================================================

    public function indexWards(Request $request)
    {
        try {
            $query = Ward::with(['district.region'])
                ->when($request->search, fn($q) => $q->search($request->search))
                ->when($request->district_id, fn($q) => $q->inDistrict($request->district_id))
                ->when($request->region_id, fn($q) => $q->inRegion($request->region_id));

            $wards = $query->orderBy('ward_name')->paginate($request->per_page ?? 20);

            return response()->json([
                'status' => 'success',
                'data' => $wards->items(),
                'meta' => [
                    'current_page' => $wards->currentPage(),
                    'total' => $wards->total(),
                    'per_page' => $wards->perPage(),
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch wards.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function storeWard(Request $request)
    {
        // --- START LOGGING ---
        Log::info('Attempting to store new Ward.', [
            'user_id' => auth()->id() ?? 'guest',
            'payload' => $request->all(),
            'ip' => $request->ip(),
        ]);
        // --- END LOGGING ---

        // 1. Prepare data before validation: Automatically generate ward_code
        // The client sends 'ward_name' and 'district_id'. We need to create 'ward_code'.
        // Use the last 10 characters of the current timestamp for a high probability of uniqueness.
        $timestamp = now()->timestamp;
        $uniqueCode = 'W' . substr($timestamp, -9); // Example: W167888777

        // Merge the generated code into the request for validation
        $request->merge(['ward_code' => $uniqueCode]);

        try {
            // 2. Validate the request, ensuring uniqueness per district for the name
            $validated = $request->validate([
                'district_id' => 'required|exists:districts,id',
                // Enforce uniqueness of ward_name only within the specific district_id
                'ward_name'   => 'required|string|max:100|unique:wards,ward_name,NULL,id,district_id,' . $request->district_id,
                // Enforce global uniqueness for the generated ward_code
                'ward_code'   => 'required|string|max:10|unique:wards,ward_code',
            ]);

            $ward = DB::transaction(function () use ($validated) {
                return Ward::create($validated);
            });

            Log::info('Ward created successfully.', ['ward_id' => $ward->id, 'ward_name' => $ward->ward_name]);

            return response()->json([
                'status' => 'success',
                'message' => 'Ward created successfully.',
                'data' => $ward->load('district.region')
            ], 201);

        } catch (ValidationException $e) {
            // 3. Handle Validation Errors (422)
            Log::warning('Ward creation validation failed.', ['errors' => $e->errors(), 'payload' => $request->all()]);

            return response()->json([
                'status' => 'error',
                // Check if the uniqueness constraint on ward_name per district was the issue
                'message' => isset($e->errors()['ward_name'])
                            ? 'Validation failed: The ward name already exists in this district.'
                            : 'Validation failed.',
                'errors' => $e->errors()
            ], 422);

        } catch (QueryException $e) {
            // 4. Handle Database Query Errors (e.g., Duplication/Integrity - 409)
            // This catches duplicate key errors (409 Conflict) not handled by the Validation class

            $errorMessage = $e->getMessage();

            if (str_contains($errorMessage, 'Duplicate entry') || str_contains($errorMessage, 'unique constraint')) {
                Log::error('Ward creation failed: Duplicate Key Conflict.', ['error' => $errorMessage, 'payload' => $request->all()]);

                // Return a 409 Conflict status code with a specific message
                return response()->json([
                    'status' => 'error',
                    'message' => 'The ward already exists in this District or the internal code is duplicated. Please try again or use a different name.',
                    'error_code' => 'WARD_DUPLICATE_CONFLICT'
                ], 409); // <-- Returns the 409 status code
            }

            // Handle other QueryExceptions
            Log::error('Ward creation failed due to general database error.', ['error' => $errorMessage, 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => 'error',
                'message' => 'A database error occurred.',
                'error' => $errorMessage
            ], 500);

        } catch (\Exception $e) {
            // 5. Handle General Exceptions (500)
            Log::error('Failed to create ward due to a server error.', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create ward.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function showWard($id)
    {
        try {
            $ward = Ward::with('district.region')->findOrFail($id);

            return response()->json([
                'status' => 'success',
                'data' => $ward
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Ward not found.'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Something went wrong.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateWard(Request $request, $id)
    {
        try {
            $ward = Ward::findOrFail($id);

            $validated = $request->validate([
                'district_id' => 'sometimes|required|exists:districts,id',
                'ward_name'   => 'sometimes|required|string|max:100|unique:wards,ward_name,' . $id . ',id,district_id,' . ($request->district_id ?? $ward->district_id),
                'ward_code'   => 'sometimes|required|string|max:10|unique:wards,ward_code,' . $id,
            ]);

            DB::transaction(function () use ($ward, $validated) {
                $ward->update($validated);
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Ward updated successfully.',
                'data' => $ward->fresh(['district.region'])
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Ward not found.'
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update ward.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroyWard($id)
    {
        try {
            $ward = Ward::findOrFail($id);

            DB::transaction(function () use ($ward) {
                $ward->delete();
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Ward deleted successfully.'
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Ward not found.'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot delete: Ward is in use.',
                'error' => $e->getMessage()
            ], 400);
        }
    }
}
