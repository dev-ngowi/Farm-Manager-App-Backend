<?php

namespace Database\Factories;

use App\Models\DataRequestCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class DataRequestCategoryFactory extends Factory
{
    protected $model = DataRequestCategory::class;

    public function definition(): array
    {
        $categories = [
            ['Livestock Demographics', 'Number, breed, age, sex, and location of animals', false],
            ['Health Records', 'All diagnosed conditions and symptoms', false],
            ['Disease Outbreaks', 'Confirmed outbreaks with location and dates', true],
            ['Vaccination Records', 'Vaccines given, dates, and batch numbers', false],
            ['Breeding Data', 'AI, natural mating, pregnancy, and calving records', false],
            ['Milk Production', 'Daily/weekly milk yield per cow', false],
            ['Weight & Growth', 'Body weight records over time', false],
            ['Mortality Records', 'Cause of death and post-mortem findings', true],
            ['Treatment History', 'Drugs used, dosage, and outcomes', false],
            ['Geolocation Data', 'GPS coordinates of farms and grazing areas', true],
            ['Farmer Profiles', 'Name, contact, farm size, and experience', true],
            ['Market Prices', 'Livestock and milk prices by region', false],
            ['Climate Data', 'Rainfall, temperature linked to farms', false],
        ];

        $data = $this->faker->unique()->randomElement($categories);

        return [
            'category_name' => $data[0],
            'description' => $data[1],
            'requires_special_approval' => $data[2],
        ];
    }
}
