<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Veterinarian extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $table = 'veterinarians';

    protected $fillable = [
        'user_id',
        'qualification_certificate',
        'license_number',
        'specialization',
        'years_experience',
        'clinic_name',
        'location_id',
        'consultation_fee',
        'is_approved',
        'approval_date',
    ];

    protected $casts = [
        'is_approved' => 'boolean',
        'approval_date' => 'date',
        'consultation_fee' => 'decimal:2',
        'years_experience' => 'integer',
    ];

    // ========================================
    // RELATIONSHIPS
    // ========================================
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    // Optional: Get full address
    public function getFullAddressAttribute(): string
    {
        if (!$this->location) return 'No location set';
        return $this->location->full_address ?? 'Location available';
    }

    // Media: Certificate, License, Clinic Photos
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('qualification_certificate')
             ->singleFile()
             ->acceptsMimeTypes(['image/jpeg', 'image/png', 'application/pdf']);

        $this->addMediaCollection('license_document')
             ->singleFile()
             ->acceptsMimeTypes(['image/jpeg', 'image/png', 'application/pdf']);

        $this->addMediaCollection('clinic_photos');
    }

    // Scope: Approved vets only
    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }

    // Scope: Available for booking
    public function scopeAvailable($query)
    {
        return $query->approved()->whereNotNull('consultation_fee');
    }
}
