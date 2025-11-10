<?php

namespace Database\Seeders;

use App\Models\DrugCatalog;
use Illuminate\Database\Seeder;

class DrugCatalogSeeder extends Seeder
{
    public function run(): void
    {
        DrugCatalog::factory(100)->create();
    }
}
