<?php

namespace Database\Seeders;

use App\Models\VaccinationSchedule;
use App\Models\Livestock;
use Illuminate\Database\Seeder;

class VaccinationScheduleSeeder extends Seeder
{
    public function run(): void
    {
        Livestock::all()->each(function ($animal) {
            VaccinationSchedule::factory(rand(2, 6))->create(['animal_id' => $animal->id]);
        });
    }
}
