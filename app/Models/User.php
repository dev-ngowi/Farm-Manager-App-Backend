<?php
namespace App\Models;
use App\Models\UserLocation;
use App\Models\Location;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\HasMedia;
use Laravel\Sanctum\HasApiTokens;  // ADD THIS LINE

class User extends Authenticatable implements HasMedia
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles, InteractsWithMedia, HasApiTokens;  // ADD HasApiTokens HERE
    
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name', 
        'firstname',
        'lastname',
        'email',
        'phone_number',
        'password',
        'is_active',
        'last_login',
    ];
    
    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];
    
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }
    
    public function mediaFolders()
    {
        return $this->hasMany(MediaFolder::class);
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
}