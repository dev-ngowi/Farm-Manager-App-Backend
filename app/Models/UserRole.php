<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserRole extends Model
{
    protected $table = 'user_roles';

    // MUST BE role_id (integer), NOT 'role' string!
    protected $fillable = [
        'user_id',
        'role_id',   // ← THIS IS CORRECT
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function role()
    {
        return $this->belongsTo(\Spatie\Permission\Models\Role::class, 'role_id');
    }
}
