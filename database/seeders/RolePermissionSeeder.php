<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Ensure roles exist with 'web' guard (from your RoleSeeder)
        $admin      = Role::firstOrCreate(['name' => 'Admin',      'guard_name' => 'web']);
        $vet        = Role::firstOrCreate(['name' => 'Vet',        'guard_name' => 'web']);
        $researcher = Role::firstOrCreate(['name' => 'Researcher', 'guard_name' => 'web']);
        $farmer     = Role::firstOrCreate(['name' => 'Farmer',     'guard_name' => 'web']);

        // ========================================
        // PERMISSIONS GROUPED BY MODULE
        // ========================================
        $permissions = [
            'Dashboard' => [
                'dashboard.view',
            ],
            'Farms' => [
                'farms.view',
                'farms.create',
                'farms.edit',
                'farms.delete',
            ],
            'Animals' => [
                'animals.view',
                'animals.create',
                'animals.edit',
                'animals.delete',
                'animals.breed',
                'animals.transfer',
            ],
            'Health & Vet' => [
                'health.view',
                'health.vaccinate',
                'health.treat',
                'health.pregnancy-check',
                'health.weigh',
            ],
            'Income & Expenses' => [
                'income.view',
                'income.create',
                'income.edit',
                'income.delete',
                'expenses.view',
                'expenses.create',
                'expenses.edit',
                'expenses.delete',
            ],
            'Reports' => [
                'reports.view',
                'reports.export',
                'reports.analytics',
            ],
            'Users & Access' => [
                'users.view',
                'users.create',
                'users.edit',
                'users.delete',
                'roles.view',
                'permissions.view',
            ],
            'Settings' => [
                'settings.general',
                'settings.backup',
                'settings.logs',
            ],
        ];

        // Create permissions + assign
        foreach ($permissions as $group => $perms) {
            foreach ($perms as $name) {
                $permission = Permission::firstOrCreate(
                    ['name' => $name, 'guard_name' => 'web'],
                    ['group' => $group] // optional: for UI grouping
                );

                // ADMIN gets ALL permissions
                $admin->givePermissionTo($permission);

                // VET: health + animals + reports
                if (str_starts_with($name, 'health.') || str_starts_with($name, 'animals.') || str_starts_with($name, 'reports.')) {
                    $vet->givePermissionTo($permission);
                }

                // RESEARCHER: view + reports + analytics
                if (str_contains($name, '.view') || str_starts_with($name, 'reports.')) {
                    $researcher->givePermissionTo($permission);
                }

                // FARMER: only own farm + basic income/expenses
                if (
                    str_starts_with($name, 'farms.view') ||
                    str_starts_with($name, 'animals.view') ||
                    str_starts_with($name, 'income.') ||
                    str_starts_with($name, 'expenses.')
                ) {
                    $farmer->givePermissionTo($permission);
                }
            }
        }

        // Optional: Give Admin a role display name or icon (if you have extra columns)
        // $admin->update(['display_name' => 'System Administrator']);

        $this->command->info('Roles & Permissions seeded successfully!');
        $this->command->info("Admin: Full Access | Vet: Health+Animals | Researcher: View+Reports | Farmer: Own Data");
    }
}
