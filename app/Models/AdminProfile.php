<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminProfile extends Model
{
    use HasFactory;

    protected $table = 'admin_profiles';
    protected $primaryKey = 'id';

    protected $fillable = [
        'user_id',
        'admin_level',
        'department',
        'assigned_regions',
        'can_approve_vets',
        'can_approve_researchers',
        'can_manage_users',
        'can_access_reports',
        'can_export_data',
        'can_modify_system_config'
    ];

    protected $casts = [
        'assigned_regions' => 'array',
        'can_approve_vets' => 'boolean',
        'can_approve_researchers' => 'boolean',
        'can_manage_users' => 'boolean',
        'can_access_reports' => 'boolean',
        'can_export_data' => 'boolean',
        'can_modify_system_config' => 'boolean',
    ];

    // ========================================
    // RELATIONSHIPS
    // ========================================
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ========================================
    // SCOPES
    // ========================================
    public function scopeSuperAdmins($query)
    {
        return $query->where('admin_level', 'Super Admin');
    }

    public function scopeCanExport($query)
    {
        return $query->where('can_export_data', true);
    }

    // ========================================
    // ACCESSORS — SWAHILI + UI READY
    // ========================================
    public function getLevelSwahiliAttribute(): string
    {
        return match ($this->admin_level) {
            'Super Admin' => 'Msimamizi Mkuu',
            'System Admin' => 'Msimamizi wa Mfumo',
            'Data Manager' => 'Meneja wa Data',
            'Support Staff' => 'Wafanyakazi wa Msaada',
            default => $this->admin_level
        };
    }

    public function getLevelBadgeAttribute(): string
    {
        return match ($this->admin_level) {
            'Super Admin' => 'bg-purple-100 text-purple-800',
            'System Admin' => 'bg-indigo-100 text-indigo-800',
            'Data Manager' => 'bg-blue-100 text-blue-800',
            'Support Staff' => 'bg-gray-100 text-gray-800',
            default => 'bg-gray-200'
        };
    }

    public function getRegionsTextAttribute(): string
    {
        if (!$this->assigned_regions) return 'Tanzania Yote';
        $names = \App\Models\Region::whereIn('id', $this->assigned_regions)
                                 ->pluck('name')
                                 ->toArray();
        return implode(', ', $names) ?: 'Hakuna';
    }

    public function getPermissionsListAttribute(): array
    {
        $perms = [];
        if ($this->can_approve_vets) $perms[] = 'Idhinisha Madaktari';
        if ($this->can_approve_researchers) $perms[] = 'Idhinisha Watafiti';
        if ($this->can_manage_users) $perms[] = 'Dhibiti Akaunti';
        if ($this->can_access_reports) $perms[] = 'Tazama Ripoti';
        if ($this->can_export_data) $perms[] = 'Pakua Data';
        if ($this->can_modify_system_config) $perms[] = 'Badilisha Mipangilio';
        return $perms;
    }

    public function getIsSuperAdminAttribute(): bool
    {
        return $this->admin_level === 'Super Admin';
    }

    // ========================================
    // PERMISSION CHECKS
    // ========================================
    public function can(string $ability): bool
    {
        return match ($ability) {
            'approve_vets' => $this->can_approve_vets,
            'approve_researchers' => $this->can_approve_researchers,
            'manage_users' => $this->can_manage_users,
            'access_reports' => $this->can_access_reports,
            'export_data' => $this->can_export_data,
            'modify_config' => $this->can_modify_system_config,
            default => false
        };
    }

    public function isHigherThan(AdminProfile $other): bool
    {
        $levels = ['Support Staff' => 1, 'Data Manager' => 2, 'System Admin' => 3, 'Super Admin' => 4];
        return $levels[$this->admin_level] > $levels[$other->admin_level];
    }
}
