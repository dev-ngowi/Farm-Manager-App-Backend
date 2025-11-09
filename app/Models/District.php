<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class District extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'districts';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Indicates if the model's ID is auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;

    /**
     * The data type of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'int';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'region_id',
        'district_name',
        'district_code',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the region that owns this district.
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'region_id');
    }

    /**
     * Scope: Search districts by name or code
     */
    public function scopeSearch($query, $term)
    {
        return $query->where('district_name', 'LIKE', "%{$term}%")
                     ->orWhere('district_code', 'LIKE', "%{$term}%");
    }

    /**
     * Scope: Filter by region
     */
    public function scopeInRegion($query, $regionId)
    {
        return $query->where('region_id', $regionId);
    }

    /**
     * Accessor: Format district name (e.g., "ilala" → "Ilala")
     */
    public function getDistrictNameAttribute($value)
    {
        return ucwords(strtolower($value));
    }

    /**
     * Get full location string: "Ilala - Dar es Salaam"
     */
    public function getFullNameAttribute(): string
    {
        return $this->district_name . ' - ' . ($this->region?->region_name ?? 'Unknown Region');
    }
}