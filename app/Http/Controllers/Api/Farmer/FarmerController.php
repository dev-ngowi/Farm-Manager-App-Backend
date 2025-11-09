<?php

namespace App\Http\Controllers\Api\Farmer;

use App\Http\Controllers\Controller;
use App\Models\Farmer;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class FarmerController extends Controller
{
    /**
     * GET /api/v1/farmer/profile
     * Get authenticated farmer's full profile
     */
    public function profile(Request $request)
    {
        try {
            $farmer = $request->user()->farmer()
                ->with([
                    'location.region',
                    'location.district',
                    'location.ward',
                    'location.street',
                    'livestock' => fn($q) => $q->withCount('milkYields')->latest()->take(5),
                    'expenses' => fn($q) => $q->latest()->take(3),
                    'income' => fn($q) => $q->latest()->take(3),
                ])
                ->withCount(['livestock', 'expenses', 'income'])
                ->firstOrFail();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'farmer' => $farmer,
                    'stats' => [
                        'total_animals' => $farmer->total_animals,
                        'milking_cows' => $farmer->milking_cows_count,
                        'today_milk_income' => round($farmer->today_milk_income, 2),
                        'monthly_profit' => round($farmer->monthly_profit, 2),
                        'experience_level' => $farmer->experience_level,
                        'top_earner' => $farmer->top_earner?->tag ?? 'N/A',
                        'costliest_animal' => $farmer->costliest_animal?->tag ?? 'N/A',
                    ],
                    'address' => $farmer->full_address,
                    'google_maps_url' => $farmer->location?->google_maps_url,
                ]
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Farmer profile not found. Please complete registration.'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to load profile.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/v1/farmer/register
     * Complete farmer registration (after user signup)
     */
    public function register(Request $request)
    {
        try {
            $user = $request->user();

            if ($user->farmer) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Farmer profile already exists.'
                ], 409);
            }

            $validated = $request->validate([
                'farm_name'         => 'required|string|max:100',
                'farm_purpose'      => 'required|string|in:Dairy,Beef,Mixed,Other',
                'total_land_acres'  => 'required|numeric|min:0.1|max:10000',
                'years_experience'  => 'required|integer|min:0|max:70',
                'location_id'       => 'required|exists:locations,id',
                'profile_photo'     => 'nullable|image|mimes:jpg,jpeg,png|max:5048',
            ]);

            $photoPath = null;
            if ($request->hasFile('profile_photo')) {
                $path = $request->file('profile_photo')->store('farmers/photos', 'public');
                $validated['profile_photo'] = $path;
            }

            $farmer = DB::transaction(function () use ($user, $validated, $photoPath) {
                return $user->farmer()->create([
                    'farm_name'         => $validated['farm_name'],
                    'farm_purpose'      => $validated['farm_purpose'],
                    'total_land_acres'  => $validated['total_land_acres'],
                    'years_experience'  => $validated['years_experience'],
                    'location_id'       => $validated['location_id'],
                    'profile_photo'     => $photoPath,
                ]);
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Farmer profile created successfully!',
                'data' => $farmer->load('location')
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
                'message' => 'Failed to register farmer.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * PUT /api/v1/farmer/profile
     * Update farmer profile
     */
    public function updateProfile(Request $request)
    {
        try {
            $farmer = $request->user()->farmer()->firstOrFail();

            $validated = $request->validate([
                'farm_name'         => 'sometimes|required|string|max:100',
                'farm_purpose'      => 'sometimes|required|string|in:Dairy,Beef,Mixed,Poultry,Piggery,Crop-Livestock,Other',
                'total_land_acres'  => 'sometimes|required|numeric|min:0.1|max:10000',
                'years_experience'  => 'sometimes|required|integer|min:0|max:70',
                'location_id'       => 'sometimes|required|exists:locations,id',
                'profile_photo'     => 'nullable|image|mimes:jpg,jpeg,png|max:5048',
            ]);

           if ($request->hasFile('profile_photo')) {
                if ($farmer->profile_photo && Storage::disk('public')->exists($farmer->profile_photo)) {
                    Storage::disk('public')->delete($farmer->profile_photo);
                }
                $validated['profile_photo'] = $request->file('profile_photo')->store('farmers/photos', 'public');
            }

            DB::transaction(function () use ($farmer, $validated) {
                $farmer->update($validated);
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Profile updated successfully.',
                'data' => $farmer->fresh(['location'])
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Farmer profile not found.'
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
                'message' => 'Failed to update profile.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/v1/farmer/dashboard
     * Farmer dashboard summary
     */
    public function dashboard(Request $request)
    {
        try {
            $farmer = $request->user()->farmer()->firstOrFail();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'profile' => $farmer,
                    'summary' => [
                        'total_livestock' => $farmer->total_animals,
                        'active_milking_cows' => $farmer->milking_cows_count,
                        'total_expenses_this_month' => round($farmer->expenses()->thisMonth()->sum('amount'), 2),
                        'total_income_this_month' => round($farmer->income()->thisMonth()->sum('amount'), 2),
                        'monthly_profit' => round($farmer->monthly_profit, 2),
                        'today_milk_income' => round($farmer->today_milk_income, 2),
                        'farm_size_acres' => $farmer->total_land_acres,
                        'years_farming' => $farmer->years_experience,
                        'experience_badge' => $farmer->experience_level,
                    ],
                    'recent_activity' => [
                        'latest_livestock' => $farmer->livestock()->latest()->take(3)->get(),
                        'pending_requests' => $farmer->serviceRequests()->where('status', 'Pending')->count(),
                        'upcoming_appointments' => $farmer->vetAppointments()->where('appointment_date', '>=', now())->count(),
                    ]
                ]
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Complete your profile first.'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Dashboard error.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}