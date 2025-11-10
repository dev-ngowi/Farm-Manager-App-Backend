<?php

namespace Database\Seeders;

use App\Models\ResearchDataRequest;
use Illuminate\Database\Seeder;

class ResearchDataRequestSeeder extends Seeder
{
    public function run(): void
    {
        ResearchDataRequest::factory(150)->create();
    }
}
