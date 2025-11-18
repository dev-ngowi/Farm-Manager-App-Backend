<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class VetChatConversation extends Model
{
    use HasFactory;

    protected $table = 'vet_chat_conversations';
    protected $primaryKey = 'conversation_id';

    protected $fillable = [
        'farmer_id', 'vet_id', 'health_id', 'subject', 'status', 'priority'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ========================================
    // RELATIONSHIPS
    // ========================================

    public function farmer(): BelongsTo
    {
        return $this->belongsTo(Farmer::class);
    }

    public function veterinarian(): BelongsTo
    {
        return $this->belongsTo(Veterinarian::class, 'vet_id');
    }

    public function healthReport(): BelongsTo
    {
        return $this->belongsTo(HealthReport::class, 'health_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(VetChatMessage::class, 'conversation_id')
                    ->orderBy('created_at');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(VetChatMessage::class, 'conversation_id')->latestOfMany();
    }

    // ========================================
    // CUSTOM METHODS
    // ========================================

    public function unreadCountFor($userId): int
    {
        return $this->messages()
            ->where('sender_user_id', '!=', $userId)
            ->where('is_read', false)
            ->count();
    }

    public function markAllAsReadFor($userId): void
    {
        $this->messages()
            ->where('sender_user_id', '!=', $userId)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
    }

    // ========================================
    // SCOPES
    // ========================================

    public function scopeActive($query)
    {
        return $query->where('status', 'Active');
    }

    public function scopeForFarmer($query, $farmerId)
    {
        return $query->where('farmer_id', $farmerId);
    }

    public function scopeForVet($query, $vetId)
    {
        return $query->where('vet_id', $vetId);
    }

    public function scopeUrgent($query)
    {
        return $query->where('priority', 'Urgent');
    }

    public function scopeUnreadForUser($query, $userId, $userType = 'farmer')
    {
        return $query->whereHas('messages', function ($q) use ($userId, $userType) {
            $senderType = $userType === 'farmer' ? 'vet' : 'farmer';
            $q->where('sender_type', $senderType)
              ->where('is_read', false);
        });
    }

    // ========================================
    // ACCESSORS (Swahili + UI ready)
    // ========================================

    public function getSubjectDisplayAttribute(): string
    {
        if ($this->subject) {
            return $this->subject;
        }

        if ($this->healthReport?->animal?->tag) {
            return "Ripoti ya Afya: " . $this->healthReport->animal->tag;
        }

        return "Mazungumzo na " . ($this->veterinarian?->user->fullname ?? 'Daktari');
    }

    public function getPrioritySwahiliAttribute(): string
    {
        return match ($this->priority) {
            'Low'     => 'Wastani',
            'Medium'  => 'Kawaida',
            'High'    => 'Haraka',
            'Urgent'  => 'Dharura',
            default   => $this->priority,
        };
    }

    public function getPriorityBadgeAttribute(): string
    {
        return match ($this->priority) {
            'Urgent'  => 'bg-red-600 text-white',
            'High'    => 'bg-orange-500 text-white',
            'Medium'  => 'bg-yellow-500 text-black',
            'Low'     => 'bg-gray-400 text-white',
            default   => 'bg-gray-300',
        };
    }

    public function getStatusSwahiliAttribute(): string
    {
        return match ($this->status) {
            'Active'   => 'Inaendelea',
            'Resolved' => 'Imesuluhishwa',
            'Closed'   => 'Imefungwa',
            default    => $this->status,
        };
    }

    public function getLastMessageTimeAttribute(): string
    {
        return $this->updated_at?->diffForHumans() ?? 'Hajaanza';
    }

    public function getUnreadCountAttribute(): int
    {
        $user = auth()->Auth::user();
        $type = $user->farmer ? 'farmer' : 'vet';
        $senderType = $type === 'farmer' ? 'vet' : 'farmer';

        return $this->messages()
            ->where('sender_type', $senderType)
            ->where('is_read', false)
            ->count();
    }

    public function getOtherParticipantAttribute()
    {
        $user = auth()->Auth::user();

        return $user->farmer
            ? $this->veterinarian?->user
            : $this->farmer?->user;
    }

    public function getAvatarUrlAttribute(): string
    {
        return $this->other_participant?->profile_photo_url ?? asset('images/avatar.png');
    }
}
