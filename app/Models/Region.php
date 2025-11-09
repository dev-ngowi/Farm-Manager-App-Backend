<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Region extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'regions';

    /**
     * The primary key associated with the table.
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
        'region_name',
        'region_code',
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
     * Get the region name with proper formatting (optional accessor)
     */
    public function getRegionNameAttribute($value)
    {
        return ucwords(strtolower($value));
    }

    /**
     * Scope a query to search by region name or code.
     */
    public function scopeSearch($query, $term)
    {
        return $query->where('region_name', 'LIKE', "%{$term}%")
                     ->orWhere('region_code', 'LIKE', "%{$term}%");
    }

    /**
     * Get districts that belong to this region (if you plan to add a districts table later)
     */
    public function districts()
    {
        return $this->hasMany(District::class);
    }
}