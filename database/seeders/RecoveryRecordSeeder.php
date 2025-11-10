<?php

namespace Database\Seeders;

use App\Models\RecoveryRecord;
use App\Models\VetAction;
use Illuminate\Database\Seeder;

class RecoveryRecordSeeder extends Seeder
{
    public function run(): void
    {
        VetAction::whereIn('action_type', ['Treatment', 'Vaccination', 'Surgery'])
            ->inRandomOrder()
            ->take(200)
            ->get()
            ->each(function ($action) {
                RecoveryRecord::factory(rand(1, 4))->create(['action_id' => $action->action_id]);
            });
    }
}
