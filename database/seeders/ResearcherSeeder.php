<?php

namespace Database\Seeders;

use App\Models\Researcher;
use Illuminate\Database\Seeder;

class ResearcherSeeder extends Seeder
{
    public function run(): void
    {
        Researcher::factory(80)->create();
    }
}
