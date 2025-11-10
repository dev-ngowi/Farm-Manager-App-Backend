<?php

namespace Database\Factories;

use App\Models\AdminProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AdminProfileFactory extends Factory
{
    protected $model = AdminProfile::class;

    public function definition(): array
    {
        $super = $this->faker->boolean(5); // 5% chance Super Admin

        return [
            'user_id' => User::factory()->create(['user_type' => 'admin']),
            'admin_level' => $super ? 'Super Admin' : $this->faker->randomElement([
                'System Admin', 'Data Manager', 'Support Staff'
            ]),
            'department' => $this->faker->randomElement([
                'IT & Systems', 'Data & Research', 'User Support', 'Veterinary Services', 'Management'
            ]),
            'assigned_regions' => $super ? null : $this->faker->randomElements([1,2,3,4,5,6,7,8,9,10], rand(2,6)),
            'can_approve_vets' => $this->faker->boolean(90),
            'can_approve_researchers' => $this->faker->boolean(85),
            'can_manage_users' => $this->faker->boolean(80),
            'can_access_reports' => true,
            'can_export_data' => $super || $this->faker->boolean(30),
            'can_modify_system_config' => $super || $this->faker->boolean(10),
        ];
    }
}
