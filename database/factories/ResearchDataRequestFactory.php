<?php

namespace Database\Factories;

use App\Models\ResearchDataRequest;
use App\Models\Researcher;
use App\Models\DataRequestCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class ResearchDataRequestFactory extends Factory
{
    protected $model = ResearchDataRequest::class;

    public function definition(): array
    {
        return [
            'researcher_id' => Researcher::approved()->inRandomOrder()->first()?->id ?? Researcher::factory(),
            'category_id' => DataRequestCategory::inRandomOrder()->first()->category_id,
            'request_title' => $this->faker->sentence(6),
            'research_purpose' => $this->faker->paragraph(4),
            'data_usage_description' => $this->faker->paragraph(3),
            'publication_intent' => $this->faker->boolean(80),
            'expected_publication_date' => $this->faker->optional(0.7)->dateTimeBetween('+3 months', '+2 years'),
            'funding_source' => $this->faker->randomElement(['USAID', 'Bill & Melinda Gates', 'SUA Grant', 'FAO', 'Self-funded']),
            'ethics_approval_certificate' => $this->faker->optional(0.9)->bothify('ETH-####/##'),
            'requested_date_range_start' => $this->faker->dateTimeBetween('-3 years'),
            'requested_date_range_end' => $this->faker->optional(0.8)->dateTimeBetween('-1 month', 'now'),
            'requested_regions' => $this->faker->randomElements([1,2,3,4,5,6,7,8,9], rand(1,5)),
            'requested_species' => $this->faker->randomElements([1,2,3], rand(1,3)),
            'anonymization_level' => $this->faker->randomElement(['Full', 'Partial']),
            'status' => $this->faker->randomElement(['Pending', 'Approved', 'Rejected', 'Completed']),
            'priority' => $this->faker->randomElement(['Medium', 'High']),
        ];
    }
}
