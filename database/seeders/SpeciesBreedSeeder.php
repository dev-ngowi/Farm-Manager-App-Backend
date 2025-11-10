<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Species;
use App\Models\Breed;

class SpeciesBreedSeeder extends Seeder
{
    public function run()
    {
        $data = [
            'Cattle' => [
                ['Friesian', 'Netherlands', 'Milk', 650, 24],
                ['Ayrshire', 'Scotland', 'Milk', 550, 24],
                ['Jersey', 'UK', 'Milk', 450, 22],
                ['Sahiwal', 'Pakistan', 'Dual-purpose', 400, 30],
                ['Boran', 'Kenya', 'Meat', 600, 36],
                ['Zebu', 'Tanzania', 'Dual-purpose', 350, 30],
            ],
            'Goat' => [
                ['Boer', 'South Africa', 'Meat', 90, 8],
                ['Galla', 'Kenya', 'Meat', 70, 10],
                ['Small East African', 'Tanzania', 'Dual-purpose', 35, 7],
            ],
            'Sheep' => [
                ['Red Maasai', 'Tanzania', 'Meat', 45, 10],
                ['Dorper', 'South Africa', 'Meat', 80, 9],
            ],
           
        ];

        foreach ($data as $speciesName => $breeds) {
            $species = Species::create(['species_name' => $speciesName]);

            foreach ($breeds as $breed) {
                Breed::create([
                    'species_id' => $species->species_id,
                    'breed_name' => $breed[0],
                    'origin' => $breed[1],
                    'purpose' => $breed[2],
                    'average_weight_kg' => $breed[3],
                    'maturity_months' => $breed[4],
                ]);
            }
        }
    }
}
