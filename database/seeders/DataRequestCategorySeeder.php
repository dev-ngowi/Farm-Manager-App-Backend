<?php

namespace Database\Seeders;

use App\Models\DataRequestCategory;
use Illuminate\Database\Seeder;

class DataRequestCategorySeeder extends Seeder
{
    public function run(): void
    {
        DataRequestCategory::factory(13)->create();
    }
}
