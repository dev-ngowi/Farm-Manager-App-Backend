<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserRole extends Model
{
    // Specify the table name (optional if follows convention)
    protected $table = 'user_roles';

    // Allow mass assignment for these fields
    protected $fillable = [
        'user_id',
        'role_id',
    ];

    /**
     * Relationship: a user role belongs to a user
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship: a user role belongs to a role
     */
    public function role()
    {
        return $this->belongsTo(Role::class);
    }
}
