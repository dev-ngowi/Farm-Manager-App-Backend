<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Mail\ResearcherApprovedMail;
use App\Models\Researcher;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ResearcherApprovalController extends Controller
{
    /**
     * Get all pending researcher approval requests
     *
     * @return \Illuminate\Http\JsonResponse
     */


    public function getApprovalStatus()
    {
        // Ensure the user is authenticated
        if (!Auth::check()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
        
        // Assuming a User model relationship: $user->researcher
        $user = Auth::user();
        $researcher = $user->researcher;

        if (!$researcher) {
            // The researcher profile entry does not exist yet.
            // This is a safety check; ideally, they'd only reach the Awaiting Approval page
            // after creating the profile. We can return 'not_submitted'.
             return response()->json([
                'status' => 'not_submitted', 
                'reason' => null,
            ], 200);
        }

        // Return the status and decline reason, which matches the ApprovalStatusModel
        return response()->json([
            'status' => $researcher->approval_status, // 'pending', 'approved', 'declined'
            'decline_reason' => $researcher->decline_reason, // Null if not declined/pending
        ], 200);
    }
    public function getPendingResearchers()
    {
        if (!Auth::user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $pendingResearchers = Researcher::with(['user'])
            ->where('approval_status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => $pendingResearchers,
            'total' => $pendingResearchers->count(),
        ]);
    }

    /**
     * Get all approved researchers
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getApprovedResearchers()
    {
        if (!Auth::user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $approvedResearchers = Researcher::with(['user', 'approvedBy'])
            ->where('approval_status', 'approved')
            ->orderBy('approved_at', 'desc')
            ->get();

        return response()->json([
            'data' => $approvedResearchers,
            'total' => $approvedResearchers->count(),
        ]);
    }

    /**
     * Get all declined researchers
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDeclinedResearchers()
    {
        if (!Auth::user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $declinedResearchers = Researcher::with(['user', 'approvedBy'])
            ->where('approval_status', 'declined')
            ->orderBy('approved_at', 'desc')
            ->get();

        return response()->json([
            'data' => $declinedResearchers,
            'total' => $declinedResearchers->count(),
        ]);
    }

    /**
     * Approve a researcher request
     *
     * @param  int  $researcherId
     * @return \Illuminate\Http\JsonResponse
     */
    public function approveResearcher($researcherId)
    {
        if (!Auth::user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        DB::beginTransaction();
        try {
            $researcher = Researcher::with('user')->findOrFail($researcherId);

            if ($researcher->approval_status === 'approved') {
                return response()->json([
                    'message' => 'Researcher already approved.',
                ], 400);
            }

            // Update researcher record
            $researcher->update([
                'approval_status' => 'approved',
                'approved_by'     => Auth::id(),
                'approved_at'     => now(),
                'decline_reason'  => null,
            ]);

            // Update user role flag
            $researcher->user->update([
                'is_researcher' => true,
            ]);

            DB::commit();

            // SEND EMAIL NOTIFICATION
            try {
                Mail::to($researcher->user->email)->queue(new ResearcherApprovedMail($researcher->user));
                Log::info('Researcher approval email queued', [
                    'user_id' => $researcher->user_id,
                    'email'   => $researcher->user->email,
                ]);
            } catch (\Exception $mailException) {
                Log::warning('Failed to queue approval email', [
                    'user_id' => $researcher->user_id,
                    'error'   => $mailException->getMessage(),
                ]);
                // Don't fail the whole approval if email fails
            }

            Log::info('Researcher approved successfully', [
                'researcher_id' => $researcher->id,
                'user_id'       => $researcher->user_id,
                'approved_by'   => Auth::id(),
            ]);

            return response()->json([
                'message'    => 'Researcher approved successfully and notification sent.',
                'researcher' => $researcher->fresh()->load(['user', 'approvedBy']),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error approving researcher', [
                'researcher_id' => $researcherId,
                'error'        => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to approve researcher.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Decline a researcher request
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $researcherId
     * @return \Illuminate\Http\JsonResponse
     */
    public function declineResearcher(Request $request, $researcherId)
    {
        if (!Auth::user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $request->validate([
            'decline_reason' => 'required|string|max:500',
        ]);

        DB::beginTransaction();
        try {
            $researcher = Researcher::with('user')->findOrFail($researcherId);

            if ($researcher->approval_status === 'declined') {
                return response()->json([
                    'message' => 'Researcher already declined.',
                ], 400);
            }

            $researcher->update([
                'approval_status' => 'declined',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
                'decline_reason' => $request->decline_reason,
            ]);

            // Delete researcher profile and revert user status
            $userId = $researcher->user_id;
            $researcher->delete();

            // Update user to remove researcher status
            User::where('id', $userId)->update([
                'is_researcher' => false,
            ]);

            DB::commit();

            Log::info('Researcher declined and deleted', [
                'researcher_id' => $researcherId,
                'user_id' => $userId,
                'declined_by' => Auth::id(),
                'reason' => $request->decline_reason,
            ]);

            return response()->json([
                'message' => 'Researcher request declined and profile removed.',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error declining researcher', [
                'researcher_id' => $researcherId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to decline researcher.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get researcher approval statistics
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getApprovalStats()
    {
        if (!Auth::user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $stats = [
            'pending' => Researcher::where('approval_status', 'pending')->count(),
            'approved' => Researcher::where('approval_status', 'approved')->count(),
            'declined' => Researcher::where('approval_status', 'declined')->count(),
            'total' => Researcher::count(),
        ];

        return response()->json($stats);
    }
}