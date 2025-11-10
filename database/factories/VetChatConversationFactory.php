<?php

namespace Database\Factories;

use App\Models\VetChatConversation;
use App\Models\Farmer;
use App\Models\Veterinarian;
use Illuminate\Database\Eloquent\Factories\Factory;

class VetChatConversationFactory extends Factory
{
    protected $model = VetChatConversation::class;

    public function definition(): array
    {
        return [
            'farmer_id' => Farmer::inRandomOrder()->first()?->id ?? Farmer::factory(),
            'vet_id' => Veterinarian::inRandomOrder()->first()?->id ?? Veterinarian::factory(),
            'health_id' => \App\Models\HealthReport::inRandomOrder()->first()?->health_id,
            'subject' => $this->faker->optional(0.6)->sentence(4),
            'status' => $this->faker->randomElement(['Active', 'Resolved', 'Closed']),
            'priority' => $this->faker->randomElement(['Low', 'Medium', 'High', 'Urgent']),
        ];
    }
}
