<?php

namespace Database\Seeders;

use App\Models\VetChatConversation;
use Illuminate\Database\Seeder;

class VetChatConversationSeeder extends Seeder
{
    public function run(): void
    {
        VetChatConversation::factory(200)->create();
    }
}
