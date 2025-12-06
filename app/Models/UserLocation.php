<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UserLocation extends Pivot
{
    use HasFactory;

    protected $table = 'user_locations';

    public $incrementing = true;

    protected $fillable = [
        'user_id',
        'location_id',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ========================================
    // Relationships
    // ========================================

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class)->withDefault();
    }

    // ========================================
    // Boot: Auto-manage primary flag
    // ========================================

    protected static function booted()
    {
        static::saved(function ($userLocation) {
            if ($userLocation->is_primary) {
                // Demote all other locations for this user
                static::where('user_id', $userLocation->user_id)
                    ->where('id', '!=', $userLocation->id)
                    ->update(['is_primary' => false]);
            }
        });
    }
}
