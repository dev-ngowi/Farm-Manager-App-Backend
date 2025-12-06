<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
        'is_approved'   => 'boolean',
        'approval_date' => 'datetime',
    ];

    // ========================================
    // RELATIONSHIPS – SAFE DELEGATION
    // ========================================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_admin_id');
    }

    /**
     * All locations belonging to the researcher (via user)
     */
    public function locations(): BelongsToMany
    {
        return $this->user()->locations();
    }

    /**
     * Primary location of the researcher – SAFE even if user not eager-loaded
     */
    public function primaryLocation(): ?Location
    {
        return $this->user()
            ->locations()
            ->wherePivot('is_primary', true)
            ->first();
    }

    /**
     * Alias for primaryLocation() – many devs use $researcher->location (singular)
     */
    public function location(): ?Location
    {
        return $this->primaryLocation();
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
    // ACCESSORS
    // ========================================

    public function getFullNameAttribute(): string
    {
        $title = $this->academic_title ? trim($this->academic_title) . ' ' : '';
        return $title . $this->user->fullname;
    }

    public function getPurposeSwahiliAttribute(): string
    {
        return match ($this->research_purpose) {
            'Academic'            => 'Utafiti wa Chuo Kikuu',
            'Commercial Research' => 'Utafiti wa Biashara',
            'Field Research'      => 'Utafiti wa Shambani',
            'Government Policy'   => 'Sera za Serikali',
            'NGO Project'         => 'Mradi wa NGO',
            default               => $this->research_purpose ?? 'Haijafafanuliwa',
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
        return $this->orcid_id ? "https://orcid.org/{$this->orcid_id}" : null;
    }

    public function getInstitutionShortAttribute(): string
    {
        $map = [
            'Sokoine University of Agriculture'                                 => 'SUA',
            'University of Dar es Salaam'                                        => 'UDSM',
            'Muhimbili University of Health and Allied Sciences'                 => 'MUHAS',
            'Nelson Mandela African Institution of Science and Technology'      => 'NM-AIST',
            'Tanzania Livestock Research Institute'                              => 'TALIRI',
        ];

        return $map[$this->affiliated_institution] ?? $this->affiliated_institution;
    }

    // ========================================
    // METHODS
    // ========================================

    public function approve(User $admin): void
    {
        $this->update([
            'is_approved'          => true,
            'approval_date'        => now(),
            'approved_by_admin_id' => $admin->id,
        ]);

        // Optional: fire notification
        // Notification::send($this->user, new ResearcherApprovedNotification($this));
    }

    public function isApproved(): bool
    {
        return $this->is_approved === true;
    }
}
