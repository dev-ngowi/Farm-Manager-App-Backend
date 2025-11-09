<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ward extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'wards';

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
        'district_id',
        'ward_name',
        'ward_code',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the district that owns this ward.
     */
    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class, 'district_id');
    }

    /**
     * Get the region through the district (convenient chain)
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'region_id', 'id')
                    ->through('district');
    }

    /**
     * Scope: Search by ward name or code
     */
    public function scopeSearch($query, $term)
    {
        return $query->where('ward_name', 'LIKE', "%{$term}%")
                     ->orWhere('ward_code', 'LIKE', "%{$term}%");
    }

    /**
     * Scope: Filter wards in a specific district
     */
    public function scopeInDistrict($query, $districtId)
    {
        return $query->where('district_id', $districtId);
    }

    /**
     * Scope: Filter wards in a specific region (via district)
     */
    public function scopeInRegion($query, $regionId)
    {
        return $query->whereHas('district', function ($q) use ($regionId) {
            $q->where('region_id', $regionId);
        });
    }

    /**
     * Accessor: Format ward name properly
     */
    public function getWardNameAttribute($value)
    {
        return ucwords(strtolower($value));
    }

    /**
     * Accessor: Full location path (e.g., "Kivule - Ilala - Dar es Salaam")
     */
    public function getFullPathAttribute(): string
    {
        $district = $this->district?->district_name ?? 'Unknown District';
        $region   = $this->district?->region?->region_name ?? 'Unknown Region';

        return "{$this->ward_name} - {$district} - {$region}";
    }

    /**
     * Accessor: Short location (e.g., "Kivule, Ilala")
     */
    public function getShortLocationAttribute(): string
    {
        $district = $this->district?->district_name ?? 'Unknown';
        return "{$this->ward_name}, {$district}";
    }
}