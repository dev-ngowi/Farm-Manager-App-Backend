<?php

namespace Database\Seeders;

use App\Models\VetChatMessage;
use App\Models\VetChatConversation;
use Illuminate\Database\Seeder;

class VetChatMessageSeeder extends Seeder
{
    public function run(): void
    {
        VetChatConversation::all()->each(function ($conv) {
            VetChatMessage::factory(rand(5, 40))->create([
                'conversation_id' => $conv->conversation_id
            ]);
        });
    }
}
