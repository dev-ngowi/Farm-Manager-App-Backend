<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Street extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'streets';

    /**
     * The primary key for the model.
     */
    protected $primaryKey = 'id';

    /**
     * Indicates if the model's ID is auto-incrementing.
     */
    public $incrementing = true;

    /**
     * The data type of the auto-incrementing ID.
     */
    protected $keyType = 'int';

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = true;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'ward_id',
        'street_name',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the ward that owns this street/mtaa.
     */
    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class, 'ward_id');
    }

    /**
     * Get the district through the ward.
     */
    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class, 'district_id', 'id')
                    ->through('ward');
    }

    /**
     * Get the region through the ward → district.
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'region_id', 'id')
                    ->through(['ward', 'district']);
    }

    /**
     * Scope: Search by street name
     */
    public function scopeSearch($query, $term)
    {
        return $query->where('street_name', 'LIKE', "%{$term}%");
    }

    /**
     * Scope: Streets in a specific ward
     */
    public function scopeInWard($query, $wardId)
    {
        return $query->where('ward_id', $wardId);
    }

    /**
     * Scope: Streets in a specific district
     */
    public function scopeInDistrict($query, $districtId)
    {
        return $query->whereHas('ward', fn($q) => $q->where('district_id', $districtId));
    }

    /**
     * Scope: Streets in a specific region
     */
    public function scopeInRegion($query, $regionId)
    {
        return $query->whereHas('ward.district', fn($q) => $q->where('region_id', $regionId));
    }

    /**
     * Accessor: Properly formatted street name
     */
    public function getStreetNameAttribute($value)
    {
        return ucwords(strtolower($value));
    }

    /**
     * Accessor: Full location path
     * Example: "Mwanambaya - Kivule - Ilala - Dar es Salaam"
     */
    public function getFullPathAttribute(): string
    {
        $ward     = $this->ward?->ward_name ?? 'Unknown Ward';
        $district = $this->ward?->district?->district_name ?? 'Unknown District';
        $region   = $this->ward?->district?->region?->region_name ?? 'Unknown Region';

        return "{$this->street_name} - {$ward} - {$district} - {$region}";
    }

    /**
     * Accessor: Short location (common in forms)
     * Example: "Mwanambaya, Kivule"
     */
    public function getShortLocationAttribute(): string
    {
        $ward = $this->ward?->ward_name ?? 'Unknown';
        return "{$this->street_name}, {$ward}";
    }

    /**
     * Accessor: For dropdowns or select options
     */
    public function getLabelAttribute(): string
    {
        return $this->street_name . ' (' . ($this->ward?->ward_name ?? 'No Ward') . ')';
    }
}