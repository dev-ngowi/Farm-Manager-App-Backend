<?php

namespace App\Http\Controllers\Api\Farmer;

use App\Http\Controllers\Controller;
use App\Models\Veterinarian;
use App\Models\User;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class VeterinarianController extends Controller
{
    // =================================================================
    // INDEX: List all approved vets (for farmers)
    // =================================================================
    public function index(Request $request)
    {
        $query = Veterinarian::approved()
            ->with(['user:id,firstname,lastname,phone', 'location'])
            ->select('vet_id', 'user_id', 'specialization', 'clinic_name', 'location_id', 'consultation_fee', 'years_experience');

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
            ->with(['user:id,firstname,lastname,phone,email', 'location'])
            ->findOrFail($vet_id);

        $vet->certificate_url = $vet->getFirstMediaUrl('qualification_certificate');
        $vet->license_url = $vet->getFirstMediaUrl('license_document');
        $vet->clinic_photos = $vet->getMedia('clinic_photos')->map(fn($m) => $m->getUrl());

        return response()->json([
            'status' => 'success',
            'data' => $vet
        ]);
    }

    // =================================================================
    // REGISTER: Vet creates profile (self-registration)
    // =================================================================
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'firstname' => 'required|string|max:100',
            'lastname' => 'required|string|max:100',
            'phone' => 'required|string|unique:users,phone',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'qualification_certificate' => 'required|file|mimes:pdf,jpg,png|max:5120',
            'license_document' => 'required|file|mimes:pdf,jpg,png|max:5120',
            'specialization' => 'required|string|max:150',
            'clinic_name' => 'required|string|max:200',
            'location_id' => 'required|exists:locations,location_id',
            'consultation_fee' => 'required|numeric|min:0',
            'years_experience' => 'required|integer|min:0|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();

        try {
            // Create User
            $user = User::create([
                'firstname' => $request->firstname,
                'lastname' => $request->lastname,
                'phone' => $request->phone,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'veterinarian',
                'is_active' => false,
            ]);

            // Create Vet
            $vet = Veterinarian::create([
                'user_id' => $user->id,
                'specialization' => $request->specialization,
                'clinic_name' => $request->clinic_name,
                'location_id' => $request->location_id,
                'consultation_fee' => $request->consultation_fee,
                'years_experience' => $request->years_experience,
                'is_approved' => false,
            ]);

            // Upload Media
            if ($request->hasFile('qualification_certificate')) {
                $vet->addMedia($request->file('qualification_certificate'))
                    ->toMediaCollection('qualification_certificate');
            }
            if ($request->hasFile('license_document')) {
                $vet->addMedia($request->file('license_document'))
                    ->toMediaCollection('license_document');
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Ombi lako la uthibitisho limepokelewa. Tafadhali subiri idhini.',
                'data' => $vet->load('user')
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Imeshindwa kusajili'], 500);
        }
    }

    // =================================================================
    // UPDATE PROFILE: Vet edits own profile
    // =================================================================
    public function updateProfile(Request $request)
    {
        $user = $request->user();
        if ($user->role !== 'veterinarian') {
            return response()->json(['status' => 'error', 'message' => 'Huna ruhusa'], 403);
        }

        $vet = Veterinarian::where('user_id', $user->id)->firstOrFail();

        $validator = Validator::make($request->all(), [
            'firstname' => 'sometimes|string|max:100',
            'lastname' => 'sometimes|string|max:100',
            'phone' => 'sometimes|string|unique:users,phone,' . $user->id,
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'specialization' => 'sometimes|string|max:150',
            'clinic_name' => 'sometimes|string|max:200',
            'location_id' => 'sometimes|exists:locations,location_id',
            'consultation_fee' => 'sometimes|numeric|min:0',
            'years_experience' => 'sometimes|integer|min:0|max:50',
            'qualification_certificate' => 'nullable|file|mimes:pdf,jpg,png|max:5120',
            'license_document' => 'nullable|file|mimes:pdf,jpg,png|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        // Update User
        $user->update($request->only(['firstname', 'lastname', 'phone', 'email']));

        // Update Vet
        $vet->update($request->only([
            'specialization', 'clinic_name', 'location_id',
            'consultation_fee', 'years_experience'
        ]));

        // Replace Media
        if ($request->hasFile('qualification_certificate')) {
            $vet->clearMediaCollection('qualification_certificate');
            $vet->addMedia($request->file('qualification_certificate'))
                ->toMediaCollection('qualification_certificate');
        }
        if ($request->hasFile('license_document')) {
            $vet->clearMediaCollection('license_document');
            $vet->addMedia($request->file('license_document'))
                ->toMediaCollection('license_document');
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Maelezo yako yamesasishwa',
            'data' => $vet->load('user')
        ]);
    }

    // =================================================================
    // APPROVE VET (Admin only)
    // =================================================================
    public function approve(Request $request, $vet_id)
    {
        if (!$request->user()->is_admin) {
            return response()->json(['status' => 'error', 'message' => 'Ruhusa imekataliwa'], 403);
        }

        $vet = Veterinarian::findOrFail($vet_id);
        $vet->update([
            'is_approved' => true,
            'approval_date' => now(),
        ]);

        $vet->user->update(['is_active' => true]);

        return response()->json([
            'status' => 'success',
            'message' => 'Daktari ameidhinishwa'
        ]);
    }

    // =================================================================
    // REJECT VET (Admin only)
    // =================================================================
    public function reject(Request $request, $vet_id)
    {
        if (!$request->user()->is_admin) {
            return response()->json(['status' => 'error', 'message' => 'Ruhusa imekataliwa'], 403);
        }

        $vet = Veterinarian::findOrFail($vet_id);
        $vet->delete(); // Soft delete

        return response()->json([
            'status' => 'success',
            'message' => 'Ombi limekataliwa'
        ]);
    }

    // =================================================================
    // PENDING VETS (Admin dashboard)
    // =================================================================
    public function pending(Request $request)
    {
        if (!$request->user()->is_admin) {
            return response()->json(['status' => 'error', 'message' => 'Ruhusa imekataliwa'], 403);
        }

        $vets = Veterinarian::where('is_approved', false)
            ->with(['user:id,firstname,lastname,phone', 'location'])
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $vets
        ]);
    }
}
