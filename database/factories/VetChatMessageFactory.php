<?php

namespace Database\Factories;

use App\Models\VetChatMessage;
use App\Models\VetChatConversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class VetChatMessageFactory extends Factory
{
    protected $model = VetChatMessage::class;

    public function definition(): array
    {
        $conversation = VetChatConversation::inRandomOrder()->first();
        $isFarmer = $this->faker->boolean();

        return [
            'conversation_id' => $conversation?->conversation_id ?? VetChatConversation::factory(),
            'sender_user_id' => $isFarmer
                ? $conversation?->farmer?->user_id ?? User::where('user_type', 'farmer')->inRandomOrder()->first()?->id
                : $conversation?->veterinarian?->user_id ?? User::where('user_type', 'vet')->inRandomOrder()->first()?->id,

            'message_text' => $this->faker->optional(0.7)->realText(200),
            'media_url' => $this->faker->optional(0.3)->imageUrl(640, 480, 'animals'),
            'media_type' => $this->faker->optional(0.3)->randomElement(['Image', 'Video', 'Audio', 'Document']),
            'is_read' => $this->faker->boolean(70),
            'read_at' => $this->faker->optional(0.7)->dateTimeBetween('-30 days'),
        ];
    }
}
