<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Define roles with 'web' guard
        $roles = ['Admin', 'Vet', 'Researcher', 'Farmer'];

        foreach ($roles as $roleName) {
            Role::firstOrCreate(
                ['name' => $roleName],
                ['guard_name' => 'web'] // Use 'web' guard for Sanctum
            );
        }

        // If you have permissions, create them too
        // Permission::firstOrCreate(['name' => 'manage users'], ['guard_name' => 'web']);
    }
}