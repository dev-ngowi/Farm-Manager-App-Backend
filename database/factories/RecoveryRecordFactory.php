<?php

namespace Database\Factories;

use App\Models\RecoveryRecord;
use App\Models\VetAction;
use Illuminate\Database\Eloquent\Factories\Factory;

class RecoveryRecordFactory extends Factory
{
    protected $model = RecoveryRecord::class;

    public function definition(): array
    {
        $status = $this->faker->randomElement([
            'Ongoing', 'Improved', 'Fully Recovered',
            'No Change', 'Worsened', 'Deceased'
        ]);

        $percentage = match ($status) {
            'Fully Recovered' => $this->faker->numberBetween(95, 100),
            'Improved'        => $this->faker->numberBetween(60, 90),
            'Ongoing'         => $this->faker->numberBetween(30, 70),
            'No Change'       => $this->faker->numberBetween(10, 40),
            'Worsened'        => $this->faker->numberBetween(0, 30),
            'Deceased'        => 0,
            default           => 50
        };

        return [
            'action_id' => VetAction::factory(),
            'recovery_status' => $status,
            'recovery_date' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'recovery_percentage' => $percentage,
            'recovery_notes' => $this->faker->optional(0.8)->paragraph(2),
            'reported_by' => $this->faker->randomElement(['Farmer', 'Veterinarian']),
        ];
    }
}
