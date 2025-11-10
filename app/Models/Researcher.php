<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Researcher extends Model
{
    use HasFactory;

    protected $table = 'researchers';
    protected $primaryKey = 'id';

    protected $fillable = [
        'user_id',
        'affiliated_institution',
        'department',
        'research_purpose',
        'research_focus_area',
        'academic_title',
        'orcid_id',
        'is_approved',
        'approval_date',
        'approved_by_admin_id'
    ];

    protected $casts = [
        'is_approved' => 'boolean',
        'approval_date' => 'date',
    ];

    // ========================================
    // RELATIONSHIPS
    // ========================================
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_admin_id');
    }

    // ========================================
    // SCOPES
    // ========================================
    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }

    public function scopePending($query)
    {
        return $query->where('is_approved', false);
    }

    public function scopeByPurpose($query, $purpose)
    {
        return $query->where('research_purpose', $purpose);
    }

    // ========================================
    // ACCESSORS — SWAHILI + UI READY
    // ========================================
    public function getFullNameAttribute(): string
    {
        return $this->academic_title
            ? "{$this->academic_title} {$this->user->fullname}"
            : $this->user->fullname;
    }

    public function getPurposeSwahiliAttribute(): string
    {
        return match ($this->research_purpose) {
            'Academic' => 'Utafiti wa Chuo Kikuu',
            'Commercial Research' => 'Utafiti wa Biashara',
            'Field Research' => 'Utafiti wa Shambani',
            'Government Policy' => 'Sera za Serikali',
            'NGO Project' => 'Mradi wa NGO',
            default => $this->research_purpose
        };
    }

    public function getStatusBadgeAttribute(): string
    {
        return $this->is_approved
            ? 'bg-green-100 text-green-800'
            : 'bg-yellow-100 text-yellow-800';
    }

    public function getStatusTextAttribute(): string
    {
        return $this->is_approved ? 'Imeidhinishwa' : 'Inasubiri Idhini';
    }

    public function getOrcidUrlAttribute(): ?string
    {
        if (!$this->orcid_id) return null;
        return "https://orcid.org/{$this->orcid_id}";
    }

    public function getInstitutionShortAttribute(): string
    {
        $short = [
            'Sokoine University of Agriculture' => 'SUA',
            'University of Dar es Salaam' => 'UDSM',
            'Muhimbili University' => 'MUHAS',
            'Nelson Mandela African Institution' => 'NM-AIST',
            'Tanzania Livestock Research Institute' => 'TALIRI',
        ];
        return $short[$this->affiliated_institution] ?? $this->affiliated_institution;
    }

    // ========================================
    // METHODS
    // ========================================
    public function approve(User $admin): void
    {
        $this->update([
            'is_approved' => true,
            'approval_date' => now(),
            'approved_by_admin_id' => $admin->id
        ]);

        // Send welcome email + SMS
        // Notification::send($this->user, new ResearcherApproved());
    }

    public function isApproved(): bool
    {
        return $this->is_approved;
    }
}
