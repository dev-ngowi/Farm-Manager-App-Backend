<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class VaccinationSchedule extends Model
{
    use HasFactory;

    protected $table = 'vaccination_schedules';
    protected $primaryKey = 'schedule_id';

    protected $fillable = [
        // Vet Planning Fields
        'animal_id', 'vaccine_name', 'disease_prevented',
        'scheduled_date', 'vet_id', 'action_id', 'notes',
        'batch_number',
        'vet_action_id',

        // Execution/Farmer Fields
        'status', 'reminder_sent',
        'completed_date',
        'administered_by_user_id',
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
        // Link to the VetAction that *created* this schedule
        return $this->belongsTo(VetAction::class, 'vet_action_id');
    }

    // Indirect link to Farmer through the animal
    public function farmer()
    {
        // Assuming Livestock model has a belongsTo relationship to Farmer
        return $this->animal->farmer;
    }

    // User who administered the vaccine (can be Farmer or Farm Manager)
    public function administeredBy(): BelongsTo
    {
        // Assuming your users are stored in the 'users' table
        return $this->belongsTo(User::class, 'administered_by_user_id');
    }


    // ========================================
    // BOOT: Auto-mark missed
    // ========================================
    protected static function booted()
    {
        static::saving(function ($schedule) {
            // Only auto-mark missed if the status is 'Pending' and the scheduled date has passed.
            if ($schedule->isDirty('scheduled_date') || $schedule->isDirty('status')) {
                if ($schedule->status === 'Pending' && $schedule->scheduled_date < today()) {
                    $schedule->status = 'Missed';
                }
            }
        });

        // Simplified the reminder logic here; complex reminder dispatch is best handled by an Observer or Job Dispatch
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

    // The 'due or overdue' scope is critical for the farmer dashboard/app view
    public function scopeDueOrOverdue($query)
    {
        return $query->where('scheduled_date', '<=', today())
                     ->whereIn('status', ['Pending', 'Missed']);
    }

    // ========================================
    // ACCESSORS (Unchanged but relying on new fields)
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

    // ========================================
    // AUXILIARY RELATIONSHIPS (Vet-Centric)
    // Note: These relationships should ideally be on the Veterinarian model,
    // but they are defined here for convenience using the 'vet_id' foreign key.
    // ========================================
    public function appointments(): HasMany
    {
        // Links this schedule to all appointments made by the linked Vet (vet_id)
        // Table: vet_appointments, Foreign Key: vet_id
        return $this->hasMany(VetAppointment::class, 'vet_id', 'vet_id');
    }

    public function todayAppointments()
    {
        return $this->appointments()->whereDate('appointment_date', today());
    }

    public function chatConversations(): HasMany
    {
        // Table: vet_chat_conversations, Foreign Key: vet_id
        return $this->hasMany(VetChatConversation::class, 'vet_id', 'vet_id');
    }

    public function activeChats()
    {
        return $this->chatConversations()->where('status', 'Active');
    }
}
