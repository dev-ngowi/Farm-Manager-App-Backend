<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\BroadcastsEvents;
use Illuminate\Broadcasting\PrivateChannel;

class VetChatMessage extends Model
{
    use HasFactory;

    protected $table = 'vet_chat_messages';
    protected $primaryKey = 'message_id';

    protected $fillable = [
        'conversation_id',
        'sender_user_id',
        'message_text',
        'media_url',
        'media_type',
        'is_read',
        'read_at'
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'is_read' => 'boolean',
        'created_at' => 'datetime',
    ];

    // ========================================
    // RELATIONSHIPS
    // ========================================
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(VetChatConversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }

    public function farmer()
    {
        return $this->sender->farmer();
    }

    public function veterinarian()
    {
        return $this->sender->veterinarian();
    }

    // ========================================
    // ACCESSORS — SWAHILI + UI READY
    // ========================================
    public function getSenderNameAttribute(): string
    {
        return $this->sender->fullname ?? 'Mtu';
    }

    public function getSenderRoleAttribute(): string
    {
        return $this->sender->is_farmer ? 'Mkulima' : 'Daktari';
    }

    public function getSenderAvatarAttribute(): string
    {
        return $this->sender->profile_photo_url ?? asset('images/avatar.png');
    }

    public function getTimeAgoAttribute(): string
    {
        return $this->created_at?->diffForHumans(['parts' => 1]) ?? 'Sasa hivi';
    }

    public function getMediaIconAttribute(): string
    {
        return match ($this->media_type) {
            'Image' => 'Camera',
            'Video' => 'Video',
            'Document' => 'Document',
            'Audio' => 'Microphone',
            default => 'Paperclip'
        };
    }

    public function getMediaSwahiliAttribute(): string
    {
        return match ($this->media_type) {
            'Image' => 'Picha',
            'Video' => 'Video',
            'Document' => 'Hati',
            'Audio' => 'Sauti',
            default => 'Media'
        };
    }

    public function getMessagePreviewAttribute(): string
    {
        if ($this->message_text) {
            return strlen($this->message_text) > 50
                ? substr($this->message_text, 0, 47) . '...'
                : $this->message_text;
        }
        return $this->media_swahili ?? 'Jumbe';
    }

    public function getIsMineAttribute(): bool
    {
        return $this->sender_user_id === auth()->id();
    }

    public function getBubbleClassAttribute(): string
    {
        return $this->is_mine
            ? 'bg-blue-600 text-white ml-auto'
            : 'bg-gray-200 text-gray-900 mr-auto';
    }

    // ========================================
    // MARK AS READ
    // ========================================
    public function markAsRead(): void
    {
        if (!$this->is_read) {
            $this->update([
                'is_read' => true,
                'read_at' => now()
            ]);
        }
    }

    // ========================================
    // BROADCASTING (Pusher / Laravel Echo)
    // ========================================
    public function broadcastOn($event)
    {
        return new PrivateChannel('vet-chat.conversation.' . $this->conversation_id);
    }
}
