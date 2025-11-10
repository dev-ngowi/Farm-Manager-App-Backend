<?php

namespace App\Models;

use Spatie\Permission\Traits\HasRoles;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\HasMedia;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements HasMedia
{
    use HasFactory, Notifiable, HasRoles, InteractsWithMedia, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'firstname',
        'lastname',
        'username',        // ← NEW: username is now fillable
        'email',
        'phone_number',
        'password',
        'is_active',
        'last_login',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Cast attributes
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login'        => 'datetime',
            'password'          => 'hashed',
            'is_active'         => 'boolean',
        ];
    }

    // ========================================
    // RELATIONSHIPS
    // ========================================
    // In app/Models/User.php — ADD THIS METHOD

    public function veterinarian()
    {
        return $this->hasOne(Veterinarian::class);
    }

    public function farmer()
    {
        return $this->hasOne(Farmer::class);
    }


public function researcher()
{
    return $this->hasOne(Researcher::class);
}

public function isResearcher(): bool
{
    return $this->researcher()->exists();
}

public function isApprovedResearcher(): bool
{
    return $this->researcher?->is_approved ?? false;
}

    public function locations()
    {
        return $this->belongsToMany(Location::class, 'user_locations')
            ->using(UserLocation::class)
            ->withPivot('id', 'is_primary', 'created_at', 'updated_at')
            ->withTimestamps();
    }

    public function userLocations()
    {
        return $this->hasMany(UserLocation::class);
    }

    public function primaryLocation()
    {
        return $this->hasOne(UserLocation::class)
            ->where('is_primary', true)
            ->with('location');
    }

    public function addLocation(Location $location, bool $isPrimary = false)
    {
        return $this->locations()->attach($location->id, [
            'is_primary' => $isPrimary
        ]);
    }

    // ========================================
    // ACCESSORS
    // ========================================
    public function getFullNameAttribute(): string
    {
        return "{$this->firstname} {$this->lastname}";
    }

    // Optional: Allow login with username OR phone OR email
    public function findForPassport($identifier)
    {
        return $this->orWhere('username', $identifier)
                    ->orWhere('phone_number', $identifier)
                    ->orWhere('email', $identifier)
                    ->first();
    }
}
