<?php

namespace Database\Seeders;

use App\Models\ResearchDataExport;
use Illuminate\Database\Seeder;

class ResearchDataExportSeeder extends Seeder
{
    public function run(): void
    {
        ResearchDataExport::factory(300)->create();
    }
}
