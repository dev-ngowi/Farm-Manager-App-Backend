<?php

namespace Database\Factories;

use App\Models\DrugCatalog;
use Illuminate\Database\Eloquent\Factories\Factory;

class DrugCatalogFactory extends Factory
{
    protected $model = DrugCatalog::class;

    public function definition(): array
    {
        $drugs = [
            // Antibiotics
            ['Oxytetracycline 20%', 'Oxytetracycline', 'Antibiotic', 'Kepro', '10ml/100kg IM', 14],
            ['Pen & Strep', 'Penicillin + Streptomycin', 'Antibiotic', 'Norbrook', '1ml/25kg IM', 21],
            ['Tylosin 200', 'Tylosin', 'Antibiotic', 'Kepro', '1ml/20kg IM', 14],
            ['Amoxicillin 15%', 'Amoxicillin', 'Antibiotic', 'MSD', '1ml/20kg IM', 14],

            // Dewormers
            ['Albendazole 10%', 'Albendazole', 'Antiparasitic', 'Kepro', '1ml/20kg Oral', 10],
            ['Ivermectin 1%', 'Ivermectin', 'Antiparasitic', 'Norvet', '0.2mg/kg SC', 28],
            ['Closantel 5%', 'Closantel', 'Antiparasitic', 'Kepro', '1ml/10kg Oral', 28],
            ['Levamisole 7.5%', 'Levamisole', 'Antiparasitic', 'Ashish', '1ml/10kg Oral', 7],

            // Anti-inflammatory
            ['Dexafen 0.1%', 'Dexamethasone', 'Anti-inflammatory', 'Kepro', '1ml/50kg IM', 5],
            ['Flumethrin Pour-On', 'Flumethrin', 'Ectoparasiticide', 'Bayer', '10ml per animal', 0],

            // Vitamins
            ['B-Complex + Liver', 'Vitamin B Complex', 'Vitamin', 'Kepro', '5ml IM', 0],
            ['ADE Injection', 'Vitamins A,D,E', 'Vitamin', 'Norvet', '5ml IM', 0],

            // Vaccines
            ['FMD Vaccine', 'Foot and Mouth Disease', 'Vaccine', 'Tanzania Veterinary Lab', '2ml SC', 0],
            ['Anthrax Spore', 'Bacillus anthracis', 'Vaccine', 'TVLA', '1ml SC', 0],
        ];

        $drug = $this->faker->randomElement($drugs);

        return [
            'drug_name' => $drug[0],
            'generic_name' => $drug[1],
            'drug_category' => $drug[2],
            'manufacturer' => $drug[3],
            'common_dosage' => $drug[4],
            'withdrawal_period_days' => $drug[5],
            'side_effects' => $this->faker->optional(0.7)->paragraph(2),
            'contraindications' => $this->faker->optional(0.6)->sentence(),
            'storage_conditions' => $this->faker->randomElement([
                'Store below 25°C, protect from light',
                'Refrigerate between 2-8°C',
                'Keep in cool dry place',
                'Do not freeze'
            ]),
            'is_prescription_only' => $this->faker->boolean(85),
        ];
    }
}
