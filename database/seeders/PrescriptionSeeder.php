<?php

namespace Database\Seeders;

use App\Models\Prescription;
use Illuminate\Database\Seeder;

class PrescriptionSeeder extends Seeder
{
    public function run(): void
    {
        Prescription::factory(300)->create();
    }
}
