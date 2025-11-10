<?php

namespace Database\Seeders;

use App\Models\AdminProfile;
use Illuminate\Database\Seeder;

class AdminProfileSeeder extends Seeder
{
    public function run(): void
    {
        // Create 1 Super Admin
        AdminProfile::factory()->create([
            'user_id' => \App\Models\User::factory()->create([
                '' => 'Dr. John Mushi',
                'phone_number' => '255712345678',
                'email' => 'superadmin@mifugo.app',
                'user_type' => 'admin'
            ])->id,
            'admin_level' => 'Super Admin',
            'department' => 'Executive Management',
            'can_export_data' => true,
            'can_modify_system_config' => true,
        ]);

        AdminProfile::factory(24)->create();
    }
}
