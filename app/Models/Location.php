<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Builder;

class Location extends Model
{
    use HasFactory;

    protected $table = 'locations';

    protected $fillable = [
        'region_id',
        'district_id',
        'ward_id',
        'street_id',
        'longitude',
        'latitude',
        'address_details',
    ];

    protected $casts = [
        'longitude'  => 'float',
        'latitude'   => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ========================================
    // RELATIONSHIPS
    // ========================================

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class)->withDefault();
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class)->withDefault();
    }

    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class)->withDefault();
    }

    public function street(): BelongsTo
    {
        return $this->belongsTo(Street::class)->withDefault();
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_locations')
                    ->using(UserLocation::class)
                    ->withPivot('id', 'is_primary', 'created_at', 'updated_at')
                    ->withTimestamps();
    }

    public function userLocations()
    {
        return $this->hasMany(UserLocation::class);
    }

    // ========================================
    // SCOPES
    // ========================================

    public function scopeWithAllRelations(Builder $query): Builder
    {
        return $query->with(['region', 'district', 'ward', 'street']);
    }

    public function scopeInRegion(Builder $query, $regionId): Builder
    {
        return $query->where('region_id', $regionId);
    }

    public function scopeInDistrict(Builder $query, $districtId): Builder
    {
        return $query->where('district_id', $districtId);
    }

    public function scopeHasCoordinates(Builder $query): Builder
    {
        return $query->whereNotNull('latitude')->whereNotNull('longitude');
    }

    // ========================================
    // ACCESSORS
    // ========================================

    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->address_details,
            $this->street?->street_name,
            $this->ward?->ward_name,
            $this->district?->district_name,
            $this->region?->region_name,
            'Tanzania',
        ]);

        return implode(', ', $parts) ?: 'Address not specified';
    }

    public function getShortAddressAttribute(): string
    {
        $parts = array_filter([
            $this->street?->street_name,
            $this->ward?->ward_name,
            $this->district?->district_name,
        ]);

        return implode(' - ', $parts) ?: 'Location not set';
    }

    public function getGoogleMapsUrlAttribute(): ?string
    {
        if ($this->latitude && $this->longitude) {
            return "https://www.google.com/maps?q={$this->latitude},{$this->longitude}";
        }
        return null;
    }

    public function getCoordinatesAttribute(): ?array
    {
        return $this->latitude && $this->longitude
            ? [$this->latitude, $this->longitude]
            : null;
    }

    public function getHasGpsAttribute(): bool
    {
        return !is_null($this->latitude) && !is_null($this->longitude);
    }
}
