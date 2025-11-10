<?php

namespace Database\Seeders;

use App\Models\VetAppointment;
use Illuminate\Database\Seeder;

class VetAppointmentSeeder extends Seeder
{
    public function run(): void
    {
        VetAppointment::factory(400)->create();
    }
}
