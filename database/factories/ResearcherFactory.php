<?php

namespace Database\Factories;

use App\Models\Researcher;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ResearcherFactory extends Factory
{
    protected $model = Researcher::class;

    public function definition(): array
    {
        $institutions = [
            'Sokoine University of Agriculture',
            'University of Dar es Salaam',
            'Tanzania Livestock Research Institute (TALIRI)',
            'Nelson Mandela African Institution of Science and Technology',
            'Ministry of Livestock and Fisheries',
            'Heifer International Tanzania',
            'FAO Tanzania',
        ];

        return [
            'user_id' => User::factory()->create(['user_type' => 'researcher']),
            'affiliated_institution' => $this->faker->randomElement($institutions),
            'department' => $this->faker->randomElement(['Animal Science', 'Veterinary Medicine', 'Epidemiology', 'Public Health', 'Genetics']),
            'research_purpose' => $this->faker->randomElement(['Academic', 'Field Research', 'Government Policy', 'NGO Project']),
            'research_focus_area' => $this->faker->randomElement([
                'East Coast Fever', 'FMD Control', 'Antimicrobial Resistance', 'Brucellosis', 'Pastoralist Livelihoods'
            ]),
            'academic_title' => $this->faker->randomElement(['Dr.', 'Prof.', 'Mr.', 'Ms.', null]),
            'orcid_id' => $this->faker->optional(0.8)->numerify('####-####-####-####'),
            'is_approved' => $this->faker->boolean(75),
            'approval_date' => $this->faker->optional(0.75)->date(),
        ];
    }
}
