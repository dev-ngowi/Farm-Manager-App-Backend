<?php

namespace Database\Factories;

use App\Models\Prescription;
use App\Models\VetAction;
use App\Models\DrugCatalog;
use Illuminate\Database\Eloquent\Factories\Factory;

class PrescriptionFactory extends Factory
{
    protected $model = Prescription::class;

    public function definition(): array
    {
        $action = VetAction::where('action_type', 'Prescription')
            ->inRandomOrder()
            ->first() ?? VetAction::factory()->create(['action_type' => 'Prescription']);

        return [
            'action_id' => $action->action_id,
            'animal_id' => $action->healthReport?->animal_id ?? \App\Models\Livestock::factory(),
            'vet_id' => $action->vet_id,
            'farmer_id' => $action->healthReport?->farmer_id ?? \App\Models\Farmer::factory(),
            'drug_id' => DrugCatalog::inRandomOrder()->first()?->drug_id,
            'drug_name_custom' => null,
            'dosage' => $this->faker->randomElement(['10ml', '2 tablets', '5ml/kg', '1 sachet']),
            'frequency' => $this->faker->randomElement(['Once daily', 'Twice daily', 'Every 8 hours', 'As needed']),
            'duration_days' => $this->faker->numberBetween(3, 14),
            'administration_route' => $this->faker->randomElement(['Oral', 'Injection', 'Topical']),
            'special_instructions' => $this->faker->optional(0.7)->paragraph(2),
            'prescribed_date' => now(),
            'start_date' => now(),
            'withdrawal_period_days' => $this->faker->optional(0.8)->numberBetween(7, 28),
            'quantity_prescribed' => $this->faker->randomFloat(2, 50, 1000),
            'prescription_status' => $this->faker->randomElement(['Active', 'Completed']),
        ];
    }
}
