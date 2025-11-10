<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VetServiceArea extends Model
{
    use HasFactory;

    protected $table = 'vet_service_areas';

    protected $fillable = [
        'vet_id',
        'region_id',
        'district_id',
        'ward_id',
        'service_radius_km',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'service_radius_km' => 'decimal:2',
    ];

    // ========================================
    // RELATIONSHIPS
    // ========================================
    public function veterinarian(): BelongsTo
    {
        return $this->belongsTo(Veterinarian::class, 'vet_id');
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }

    // ========================================
    // SCOPES
    // ========================================
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeCoveringWard($query, $wardId)
    {
        return $query->where('ward_id', $wardId);
    }

    public function scopeCoveringDistrict($query, $districtId)
    {
        return $query->where(function ($q) use ($districtId) {
            $q->where('district_id', $districtId)
              ->orWhereNull('district_id');
        });
    }

    // ========================================
    // ACCESSORS
    // ========================================
    public function getAreaNameAttribute(): string
    {
        if ($this->ward) return $this->ward->name . ' Ward';
        if ($this->district) return $this->district->name . ' District';
        if ($this->region) return $this->region->name . ' Region';
        return 'Nationwide';
    }

    public function getCoverageLevelAttribute(): string
    {
        if ($this->ward_id) return 'Ward';
        if ($this->district_id) return 'District';
        if ($this->region_id) return 'Region';
        return 'Nationwide';
    }
}
