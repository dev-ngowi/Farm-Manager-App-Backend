<?php

namespace App\Events;

use App\Models\VetChatMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class VetChatMessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public $message;

    public function __construct(VetChatMessage $message)
    {
        $this->message = $message->load('sender');
    }

    public function broadcastOn()
    {
        return new PrivateChannel('vet-chat.conversation.' . $this->message->conversation_id);
    }

    public function broadcastWith()
    {
        return [
            'message' => $this->message,
            'html' => view('chat.message_bubble', ['message' => $this->message])->render()
        ];
    }
}
