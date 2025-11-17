<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ExpenseCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
{
    foreach (ExpenseCategory::defaultCategories() as $cat) {
        ExpenseCategory::create($cat);
    }
}
}
