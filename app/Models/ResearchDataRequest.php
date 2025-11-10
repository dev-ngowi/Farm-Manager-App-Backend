<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class ResearchDataRequest extends Model
{
    use HasFactory;

    protected $table = 'research_data_requests';
    protected $primaryKey = 'request_id';

    protected $fillable = [
        'researcher_id', 'category_id', 'request_title', 'research_purpose',
        'data_usage_description', 'publication_intent', 'expected_publication_date',
        'funding_source', 'ethics_approval_certificate', 'requested_date_range_start',
        'requested_date_range_end', 'requested_regions', 'requested_species',
        'requested_data_fields', 'anonymization_level', 'status', 'priority',
        'reviewed_by_admin_id', 'review_date', 'approval_notes', 'rejection_reason',
        'data_access_granted_date', 'data_access_expires_date'
    ];

    protected $casts = [
        'requested_regions' => 'array',
        'requested_species' => 'array',
        'requested_data_fields' => 'array',
        'publication_intent' => 'boolean',
        'requested_date_range_start' => 'date',
        'requested_date_range_end' => 'date',
        'review_date' => 'date',
        'data_access_granted_date' => 'date',
        'data_access_expires_date' => 'date',
    ];

    // ========================================
    // RELATIONSHIPS
    // ========================================
    public function researcher(): BelongsTo
    {
        return $this->belongsTo(Researcher::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(DataRequestCategory::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_admin_id');
    }

    // ========================================
    // SCOPES
    // ========================================
    public function scopePending($query)
    {
        return $query->where('status', 'Pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'Approved');
    }

    public function scopeActiveAccess($query)
    {
        return $query->where('status', 'Approved')
                     ->where('data_access_expires_date', '>=', today());
    }

    // ========================================
    // ACCESSORS — SWAHILI + UI READY
    // ========================================
    public function getStatusSwahiliAttribute(): string
    {
        return match ($this->status) {
            'Pending' => 'Inasubiri',
            'Under Review' => 'Inachunguzwa',
            'Approved' => 'Imeidhinishwa',
            'Rejected' => 'Imekataliwa',
            'Data Prepared' => 'Data Imetayarishwa',
            'Completed' => 'Imekamilika',
            'Withdrawn' => 'Imeondolewa',
            default => $this->status
        };
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'Approved' => 'bg-green-100 text-green-800',
            'Rejected' => 'bg-red-100 text-red-800',
            'Pending', 'Under Review' => 'bg-yellow-100 text-yellow-800',
            'Data Prepared', 'Completed' => 'bg-blue-100 text-blue-800',
            'Withdrawn' => 'bg-gray-100 text-gray-800',
            default => 'bg-gray-200'
        };
    }

    public function getPrioritySwahiliAttribute(): string
    {
        return match ($this->priority) {
            'High' => 'Haraka',
            'Medium' => 'Kawaida',
            'Low' => 'Wastani',
            default => $this->priority
        };
    }

    public function getAnonymizationSwahiliAttribute(): string
    {
        return match ($this->anonymization_level) {
            'Full' => 'Kamilifu (Hakuna Jina)',
            'Partial' => 'Sehemu (Jina Limefichwa)',
            'None' => 'Hakuna (Majina Yote)',
            default => $this->anonymization_level
        };
    }

    public function getDateRangeTextAttribute(): string
    {
        if (!$this->requested_date_range_start) return 'Hakuna';
        $start = $this->requested_date_range_start->format('d/m/Y');
        $end = $this->requested_date_range_end?->format('d/m/Y') ?? 'Sasa';
        return "$start - $end";
    }

    public function getRegionsTextAttribute(): string
    {
        if (!$this->requested_regions) return 'Tanzania Yote';
        $names = \App\Models\Region::whereIn('id', $this->requested_regions)->pluck('name')->toArray();
        return implode(', ', $names) ?: 'Hakuna';
    }

    public function getAccessStatusAttribute(): string
    {
        if (!$this->data_access_granted_date) return 'Haijapewa';
        if ($this->data_access_expires_date < today()) return 'Imeisha';
        $days = today()->diffInDays($this->data_access_expires_date);
        return "Inatumika · Imepungua siku $days";
    }

    // ========================================
    // METHODS
    // ========================================
    public function approve(User $admin, ?string $notes = null, ?Carbon $expires = null): void
    {
        $this->update([
            'status' => 'Approved',
            'reviewed_by_admin_id' => $admin->id,
            'review_date' => now(),
            'approval_notes' => $notes,
            'data_access_granted_date' => now(),
            'data_access_expires_date' => $expires ?? now()->addMonths(12),
        ]);
    }

    public function reject(User $admin, string $reason): void
    {
        $this->update([
            'status' => 'Rejected',
            'reviewed_by_admin_id' => $admin->id,
            'review_date' => now(),
            'rejection_reason' => $reason,
        ]);
    }

    public function markDataPrepared(): void
    {
        $this->update(['status' => 'Data Prepared']);
    }

    public function isExpired(): bool
    {
        return $this->data_access_expires_date && $this->data_access_expires_date < today();
    }
}
