<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class VaccinationSchedule extends Model
{
    use HasFactory;

    protected $table = 'vaccination_schedules';
    protected $primaryKey = 'schedule_id';

    protected $fillable = [
        'animal_id', 'vaccine_name', 'disease_prevented',
        'scheduled_date', 'status', 'reminder_sent',
        'vet_id', 'completed_date', 'action_id', 'notes'
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'completed_date' => 'date',
        'reminder_sent' => 'boolean',
    ];

    // ========================================
    // RELATIONSHIPS
    // ========================================
    public function animal(): BelongsTo
    {
        return $this->belongsTo(Livestock::class, 'animal_id');
    }

    public function veterinarian(): BelongsTo
    {
        return $this->belongsTo(Veterinarian::class, 'vet_id');
    }

    public function vetAction(): BelongsTo
    {
        return $this->belongsTo(VetAction::class, 'action_id');
    }

    public function farmer()
    {
        return $this->animal()->first()?->farmer();
    }

    // ========================================
    // BOOT: Auto-mark missed + send reminders
    // ========================================
    protected static function booted()
    {
        static::saving(function ($schedule) {
            if ($schedule->status === 'Pending' && $schedule->scheduled_date < today()) {
                $schedule->status = 'Missed';
            }
        });

        static::created(function ($schedule) {
            if ($schedule->scheduled_date->diffInDays(today(), false) <= 3 && !$schedule->reminder_sent) {
                // Trigger SMS/Notification (you can dispatch job here)
                // dispatch(new SendVaccineReminder($schedule));
            }
        });
    }

    // ========================================
    // SCOPES
    // ========================================
    public function scopeUpcoming($query, $days = 7)
    {
        return $query->where('status', 'Pending')
                     ->whereBetween('scheduled_date', [today(), today()->addDays($days)]);
    }

    public function scopeToday($query)
    {
        return $query->where('status', 'Pending')
                     ->whereDate('scheduled_date', today());
    }

    public function scopeMissed($query)
    {
        return $query->where('status', 'Missed');
    }

    public function scopeForFarmer($query, $farmerId)
    {
        return $query->whereHas('animal.farmer', fn($q) => $q->where('id', $farmerId));
    }

    // ========================================
    // ACCESSORS
    // ========================================
    public function getStatusSwahiliAttribute(): string
    {
        return match ($this->status) {
            'Pending'      => 'Inasubiri',
            'Completed'    => 'Imekamilika',
            'Missed'       => 'Imekosa',
            'Rescheduled'  => 'Imeahirishwa',
            default        => $this->status
        };
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'Pending'      => 'bg-yellow-100 text-yellow-800',
            'Completed'    => 'bg-green-100 text-green-800',
            'Missed'       => 'bg-red-100 text-red-800',
            'Rescheduled'  => 'bg-orange-100 text-orange-800',
            default        => 'bg-gray-100'
        };
    }

    public function getDaysUntilAttribute(): ?int
    {
        if ($this->status !== 'Pending') return null;
        return today()->diffInDays($this->scheduled_date, false);
    }

    public function getReminderTextAttribute(): string
    {
        $days = $this->days_until;
        if ($days === null) return '';
        if ($days < 0) return "Imepita kwa siku " . abs($days);
        if ($days == 0) return "Leo";
        if ($days == 1) return "Kesho";
        return "Baada ya siku $days";
    }

    public function getDiseaseSwahiliAttribute(): string
    {
        $map = [
            'Foot and Mouth Disease' => 'Magonjwa ya Miguu na Mdomo',
            'Brucellosis' => 'Homa ya Brucella',
            'Anthrax' => 'Kimeta',
            'Lumpy Skin Disease' => 'Magonjwa ya Ngozi',
            'Rabies' => 'Kichaa cha Mbwa',
            'Rift Valley Fever' => 'Homa ya Bonde la Ufa',
        ];
        return $map[$this->disease_prevented] ?? $this->disease_prevented;
    }

    public function getVaccineMessageAttribute(): string
    {
        $animal = $this->animal?->tag ?? 'Ng’ombe';
        $disease = $this->getDiseaseSwahiliAttribute();
        $date = $this->scheduled_date->format('d/m/Y');
        return "CHANJO: $animal atachanjwa $disease tarehe $date. Tafadhali jiandae.";
    }

    public function appointments()
{
    return $this->hasMany(VetAppointment::class, 'vet_id');
}

public function todayAppointments()
{
    return $this->appointments()->today();
}

public function chatConversations()
{
    return $this->hasMany(VetChatConversation::class, 'vet_id');
}

public function activeChats()
{
    return $this->chatConversations()->active();
}
}
