<?php

namespace App\Http\Controllers\Api\Vet;

use App\Http\Controllers\Controller;
use App\Models\Veterinarian;
use App\Models\User;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class VeterinarianController extends Controller
{
    // =================================================================
    // INDEX: List all approved vets (for farmers)
    // =================================================================
    public function index(Request $request)
    {
        $query = Veterinarian::approved()
            ->with(['user:id,firstname,lastname,phone_number', 'location'])
            ->select('id', 'user_id', 'specialization', 'clinic_name', 'location_id', 'consultation_fee', 'years_experience');

        if ($request->filled('specialization')) {
            $query->where('specialization', 'like', '%' . $request->specialization . '%');
        }
        if ($request->filled('location_id')) {
            $query->where('location_id', $request->location_id);
        }

        $vets = $query->get();

        return response()->json([
            'status' => 'success',
            'data' => $vets
        ]);
    }

    // =================================================================
    // SHOW: Single vet profile (public)
    // =================================================================
    public function show($vet_id)
    {
        $vet = Veterinarian::approved()
            ->with(['user:id,firstname,lastname,phone_number,email', 'location'])
            ->findOrFail($vet_id);

        // Add media URLs
        $vet->certificate_url = $vet->getFirstMediaUrl('qualification_certificate');
        $vet->license_url = $vet->getFirstMediaUrl('license_document');
        $vet->clinic_photos = $vet->getMedia('clinic_photos')->map(fn($m) => $m->getUrl());

        return response()->json([
            'status' => 'success',
            'data' => $vet
        ]);
    }

    // =================================================================
    // REGISTER/CREATE VET PROFILE (Authenticated user with role=vet)
    // This is called after user has already registered and selected "vet" role
    // =================================================================
    public function store(Request $request)
    {
        $user = $request->user();

        // Verify user has vet role
        if ($user->role !== 'Vet' && $user->role !== 'vet') {
            return response()->json([
                'status' => 'error',
                'message' => 'Only users with Vet role can create veterinarian profiles'
            ], 403);
        }

        // Check if vet profile already exists
        $existingVet = Veterinarian::where('user_id', $user->id)->first();
        if ($existingVet) {
            return response()->json([
                'status' => 'error',
                'message' => 'Veterinarian profile already exists for this user'
            ], 409);
        }

        // Validate request
        $validator = Validator::make($request->all(), [
            'qualification_certificate' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'license_document' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'license_number' => 'required|string|max:100|unique:veterinarians,license_number',
            'specialization' => 'required|string|max:255',
            'clinic_name' => 'required|string|max:255',
            'location_id' => 'required|exists:locations,location_id',
            'consultation_fee' => 'required|numeric|min:0|max:9999999.99',
            'years_experience' => 'required|integer|min:0|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            Log::info('Creating veterinarian profile', [
                'user_id' => $user->id,
                'license_number' => $request->license_number
            ]);

            // Create Veterinarian Profile
            $vet = Veterinarian::create([
                'user_id' => $user->id,
                'license_number' => $request->license_number,
                'specialization' => $request->specialization,
                'clinic_name' => $request->clinic_name,
                'location_id' => $request->location_id,
                'consultation_fee' => $request->consultation_fee,
                'years_experience' => $request->years_experience,
                'qualification_certificate' => '', // Placeholder, will be updated via media
                'is_approved' => false, // Requires admin approval
            ]);

            // Upload Qualification Certificate
            if ($request->hasFile('qualification_certificate')) {
                $vet->addMedia($request->file('qualification_certificate'))
                    ->toMediaCollection('qualification_certificate');

                // Update the path in database
                $certPath = $vet->getFirstMediaUrl('qualification_certificate');
                $vet->update(['qualification_certificate' => $certPath]);
            }

            // Upload License Document
            if ($request->hasFile('license_document')) {
                $vet->addMedia($request->file('license_document'))
                    ->toMediaCollection('license_document');
            }

            // Upload optional clinic photos if provided
            if ($request->hasFile('clinic_photos')) {
                foreach ($request->file('clinic_photos') as $photo) {
                    $vet->addMedia($photo)->toMediaCollection('clinic_photos');
                }
            }

            // Update user's has_completed_details flag
            $user->update(['has_completed_details' => true]);

            DB::commit();

            Log::info('Veterinarian profile created successfully', ['vet_id' => $vet->id]);

            return response()->json([
                'status' => 'success',
                'message' => 'Your veterinarian profile has been submitted for approval. You will be notified once approved.',
                'data' => [
                    'user' => $user->only(['id', 'firstname', 'lastname', 'email', 'phone_number', 'role', 'has_completed_details']),
                    'veterinarian' => $vet->load('location')
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to create veterinarian profile', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create veterinarian profile. Please try again.',
                'debug' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    // =================================================================
    // UPDATE PROFILE: Vet edits own profile
    // =================================================================
    public function update(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'Vet' && $user->role !== 'vet') {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized'
            ], 403);
        }

        $vet = Veterinarian::where('user_id', $user->id)->first();

        if (!$vet) {
            return response()->json([
                'status' => 'error',
                'message' => 'Veterinarian profile not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'license_number' => 'sometimes|string|max:100|unique:veterinarians,license_number,' . $vet->id,
            'specialization' => 'sometimes|string|max:255',
            'clinic_name' => 'sometimes|string|max:255',
            'location_id' => 'sometimes|exists:locations,location_id',
            'consultation_fee' => 'sometimes|numeric|min:0|max:9999999.99',
            'years_experience' => 'sometimes|integer|min:0|max:50',
            'qualification_certificate' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'license_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'clinic_photos.*' => 'nullable|file|mimes:jpg,jpeg,png|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Update vet profile
            $vet->update($request->only([
                'license_number',
                'specialization',
                'clinic_name',
                'location_id',
                'consultation_fee',
                'years_experience'
            ]));

            // Replace Qualification Certificate if uploaded
            if ($request->hasFile('qualification_certificate')) {
                $vet->clearMediaCollection('qualification_certificate');
                $vet->addMedia($request->file('qualification_certificate'))
                    ->toMediaCollection('qualification_certificate');

                $certPath = $vet->getFirstMediaUrl('qualification_certificate');
                $vet->update(['qualification_certificate' => $certPath]);
            }

            // Replace License Document if uploaded
            if ($request->hasFile('license_document')) {
                $vet->clearMediaCollection('license_document');
                $vet->addMedia($request->file('license_document'))
                    ->toMediaCollection('license_document');
            }

            // Add new clinic photos (don't clear existing ones)
            if ($request->hasFile('clinic_photos')) {
                foreach ($request->file('clinic_photos') as $photo) {
                    $vet->addMedia($photo)->toMediaCollection('clinic_photos');
                }
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Profile updated successfully',
                'data' => $vet->load('user', 'location')
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to update veterinarian profile', [
                'vet_id' => $vet->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update profile'
            ], 500);
        }
    }

    // =================================================================
    // MY PROFILE: Get authenticated vet's profile
    // =================================================================
    public function myProfile(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'Vet' && $user->role !== 'vet') {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized'
            ], 403);
        }

        $vet = Veterinarian::where('user_id', $user->id)
            ->with(['user', 'location'])
            ->first();

        if (!$vet) {
            return response()->json([
                'status' => 'error',
                'message' => 'Veterinarian profile not found'
            ], 404);
        }

        // Add media URLs
        $vet->certificate_url = $vet->getFirstMediaUrl('qualification_certificate');
        $vet->license_url = $vet->getFirstMediaUrl('license_document');
        $vet->clinic_photos = $vet->getMedia('clinic_photos')->map(fn($m) => $m->getUrl());

        return response()->json([
            'status' => 'success',
            'data' => $vet
        ]);
    }

    // =================================================================
    // APPROVE VET (Admin only)
    // =================================================================
    public function approve(Request $request, $vet_id)
    {
        $user = $request->user();

        if (!$user->is_admin && $user->role !== 'Admin') {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        $vet = Veterinarian::findOrFail($vet_id);

        $vet->update([
            'is_approved' => true,
            'approval_date' => now(),
        ]);

        // Activate the user account
        $vet->user->update(['is_active' => true]);

        Log::info('Veterinarian approved', [
            'vet_id' => $vet_id,
            'approved_by' => $user->id
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Veterinarian approved successfully'
        ]);
    }

    // =================================================================
    // REJECT VET (Admin only)
    // =================================================================
    public function reject(Request $request, $vet_id)
    {
        $user = $request->user();

        if (!$user->is_admin && $user->role !== 'Admin') {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'reason' => 'sometimes|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $vet = Veterinarian::findOrFail($vet_id);

        Log::info('Veterinarian rejected', [
            'vet_id' => $vet_id,
            'rejected_by' => $user->id,
            'reason' => $request->reason
        ]);

        // Soft delete
        $vet->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Veterinarian application rejected'
        ]);
    }

    // =================================================================
    // PENDING VETS (Admin dashboard)
    // =================================================================
    public function pending(Request $request)
    {
        $user = $request->user();

        if (!$user->is_admin && $user->role !== 'Admin') {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        $vets = Veterinarian::where('is_approved', false)
            ->with(['user:id,firstname,lastname,phone_number,email', 'location'])
            ->orderBy('created_at', 'desc')
            ->get();

        // Add media URLs for each vet
        $vets->each(function ($vet) {
            $vet->certificate_url = $vet->getFirstMediaUrl('qualification_certificate');
            $vet->license_url = $vet->getFirstMediaUrl('license_document');
        });

        return response()->json([
            'status' => 'success',
            'data' => $vets
        ]);
    }

    // =================================================================
    // DELETE CLINIC PHOTO
    // =================================================================
    public function deleteClinicPhoto(Request $request, $media_id)
    {
        $user = $request->user();
        $vet = Veterinarian::where('user_id', $user->id)->firstOrFail();

        $media = $vet->getMedia('clinic_photos')->where('id', $media_id)->first();

        if (!$media) {
            return response()->json([
                'status' => 'error',
                'message' => 'Photo not found'
            ], 404);
        }

        $media->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Photo deleted successfully'
        ]);
    }
}
