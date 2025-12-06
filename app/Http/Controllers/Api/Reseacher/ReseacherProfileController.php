<?php

namespace App\Http\Controllers\Api\Reseacher;

use App\Http\Controllers\Controller;
use App\Models\Researcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ReseacherProfileController extends Controller
{
    /**
     * Display the authenticated researcher's profile.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function show()
    {
        $user = Auth::user();

        if (!$user || !$user->isResearcher()) {
            return response()->json([
                'message' => 'Unauthorized or researcher profile not yet initialized.',
            ], 403);
        }

        $researcher = $user->researcher()->with(['user', 'approvedBy'])->first();

        return response()->json([
            'researcher' => $researcher,
            'user' => $user->only(['id', 'firstname', 'lastname', 'email', 'phone_number']),
        ]);
    }

    /**
     * Handle both initial registration (creation) and subsequent updates of the researcher's profile.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $researcher = $user->researcher;
        $isRegistration = !$researcher;
        $researcherId = $researcher->id ?? null;

        // Validation
        $data = $request->validate([
            'affiliated_institution' => ['required', 'string', 'max:255'],
            'department' => ['required', 'string', 'max:255'],
            'research_purpose' => ['required', 'string', Rule::in($this->getResearchPurposesArray())],
            'research_focus_area' => ['required', 'string', 'max:255'],
            'academic_title' => ['nullable', 'string', 'max:50'],
            'orcid_id' => [
                'nullable',
                'string',
                'max:50',
                'regex:/^\d{4}-\d{4}-\d{4}-\d{3}[0-9X]$/i',
                Rule::unique('researchers')->ignore($researcherId),
            ],
        ]);

        $updateData = [
            'affiliated_institution' => $data['affiliated_institution'],
            'department' => $data['department'],
            'research_purpose' => $data['research_purpose'],
            'research_focus_area' => $data['research_focus_area'],
            'academic_title' => $data['academic_title'] ?? null,
            'orcid_id' => $data['orcid_id'] ?? null,
        ];

        // Upsert
        if ($isRegistration) {
            $researcher = Researcher::create(array_merge($updateData, [
                'user_id' => $user->id,
            ]));
            $message = 'Researcher profile registered successfully. Awaiting approval.';
        } else {
            $researcher->update($updateData);
            $message = 'Researcher profile updated successfully.';
        }

        // ⭐ CRITICAL: Return the structure that matches your Flutter app expectations
        return response()->json([
            'message' => $message,
            'researcher' => $researcher->fresh()->load('user', 'approvedBy'),
            // Include user data for easier frontend processing
            'user' => [
                'id' => $user->id,
                'firstname' => $user->firstname,
                'lastname' => $user->lastname,
                'email' => $user->email,
                'phone_number' => $user->phone_number,
                'has_completed_details' => true, // Mark as completed
            ],
        ]);
    }

    /**
     * ⭐ NEW: Public endpoint to return available research purposes
     * This method was missing but referenced in your routes!
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getResearchPurposes()
    {
        return response()->json([
            'data' => $this->getResearchPurposesArray()
        ]);
    }

    /**
     * Helper method to return the predefined list of research purpose options.
     * Renamed to avoid confusion with the public endpoint method.
     *
     * @return array
     */
    private function getResearchPurposesArray(): array
    {
        return [
            'Academic',
            'Commercial Research',
            'Field Research',
            'Government Policy',
            'NGO Project',
        ];
    }
}
