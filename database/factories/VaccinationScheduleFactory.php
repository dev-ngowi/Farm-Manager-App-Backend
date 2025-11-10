<?php

namespace Database\Factories;

use App\Models\VaccinationSchedule;
use App\Models\Livestock;
use Illuminate\Database\Eloquent\Factories\Factory;

class VaccinationScheduleFactory extends Factory
{
    protected $model = VaccinationSchedule::class;

    public function definition(): array
    {
        $vaccines = [
            ['name' => 'FMD Vaccine', 'disease' => 'Foot and Mouth Disease'],
            ['name' => 'Brucella S19', 'disease' => 'Brucellosis'],
            ['name' => 'Anthrax Spore Vaccine', 'disease' => 'Anthrax'],
            ['name' => 'LSD Vaccine', 'disease' => 'Lumpy Skin Disease'],
            ['name' => 'Rabies Vaccine', 'disease' => 'Rabies'],
        ];

        $vaccine = $this->faker->randomElement($vaccines);
        $date = $this->faker->dateTimeBetween('-30 days', '+90 days');

        return [
            'animal_id' => Livestock::factory(),
            'vaccine_name' => $vaccine['name'],
            'disease_prevented' => $vaccine['disease'],
            'scheduled_date' => $date,
            'status' => $date < now() ? $this->faker->randomElement(['Completed', 'Missed']) : 'Pending',
            'reminder_sent' => $this->faker->boolean(70),
            'vet_id' => \App\Models\Veterinarian::inRandomOrder()->first()?->id,
            'completed_date' => $date < now() ? $this->faker->optional(0.6)->dateTimeBetween($date, 'now') : null,
            'notes' => $this->faker->optional(0.4)->sentence(),
        ];
    }
}
