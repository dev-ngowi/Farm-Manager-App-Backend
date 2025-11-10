<?php

namespace Database\Factories;

use App\Models\VetAppointment;
use App\Models\Farmer;
use App\Models\Veterinarian;
use Illuminate\Database\Eloquent\Factories\Factory;

class VetAppointmentFactory extends Factory
{
    protected $model = VetAppointment::class;

    public function definition(): array
    {
        $date = $this->faker->dateTimeBetween('-10 days', '+30 days');
        $isPast = $date < now();

        return [
            'farmer_id' => Farmer::inRandomOrder()->first()?->id ?? Farmer::factory(),
            'vet_id' => Veterinarian::inRandomOrder()->first()?->id ?? Veterinarian::factory(),
            'animal_id' => \App\Models\Livestock::inRandomOrder()->first()?->id,
            'health_id' => \App\Models\HealthReport::inRandomOrder()->first()?->health_id,
            'appointment_type' => $this->faker->randomElement([
                'Routine Checkup', 'Vaccination', 'Follow-up', 'Emergency', 'Consultation'
            ]),
            'appointment_date' => $date,
            'appointment_time' => $this->faker->optional(0.8)->time(),
            'location_type' => $this->faker->randomElement(['Farm Visit', 'Clinic Visit']),
            'farm_location_id' => \App\Models\Location::inRandomOrder()->first()?->id,
            'status' => $isPast
                ? $this->faker->randomElement(['Completed', 'No Show', 'Cancelled'])
                : $this->faker->randomElement(['Scheduled', 'Confirmed']),
            'estimated_duration_minutes' => $this->faker->numberBetween(30, 120),
            'fee_charged' => $this->faker->optional(0.7)->numberBetween(20000, 200000),
            'payment_status' => $this->faker->randomElement(['Paid', 'Pending', 'Waived']),
            'notes' => $this->faker->optional(0.6)->paragraph(),
        ];
    }
}
