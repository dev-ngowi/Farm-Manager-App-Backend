<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class VetAppointment extends Model
{
    use HasFactory;

    protected $table = 'vet_appointments';
    protected $primaryKey = 'appointment_id';

    protected $fillable = [
        'farmer_id', 'vet_id', 'animal_id', 'health_id',
        'appointment_type', 'appointment_date', 'appointment_time',
        'location_type', 'farm_location_id', 'status',
        'cancellation_reason', 'estimated_duration_minutes',
        'actual_start_time', 'actual_end_time',
        'fee_charged', 'payment_status', 'notes'
    ];

    protected $casts = [
        'appointment_date' => 'date',
        'appointment_time' => 'datetime:H:i',
        'actual_start_time' => 'datetime',
        'actual_end_time' => 'datetime',
        'fee_charged' => 'decimal:2',
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

    public function animal(): BelongsTo
    {
        return $this->belongsTo(Livestock::class, 'animal_id');
    }

    public function healthReport(): BelongsTo
    {
        return $this->belongsTo(HealthReport::class, 'health_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'farm_location_id');
    }

    // ========================================
    // BOOT: Auto-reminders & status
    // ========================================
    protected static function booted()
    {
        static::creating(function ($appt) {
            if (!$appt->appointment_time) {
                $appt->appointment_time = '09:00:00';
            }
        });

        static::created(function ($appt) {
            // Dispatch SMS 24h before
            // SendAppointmentReminder::dispatch($appt)->delay(now()->addHours(24));
        });
    }

    // ========================================
    // SCOPES
    // ========================================
    public function scopeToday($query)
    {
        return $query->where('appointment_date', today());
    }

    public function scopeUpcoming($query, $days = 7)
    {
        return $query->whereBetween('appointment_date', [today(), today()->addDays($days)])
                     ->whereIn('status', ['Scheduled', 'Confirmed']);
    }

    public function scopeForVet($query, $vetId)
    {
        return $query->where('vet_id', $vetId);
    }

    public function scopeForFarmer($query, $farmerId)
    {
        return $query->where('farmer_id', $farmerId);
    }

    public function scopeEmergency($query)
    {
        return $query->where('appointment_type', 'Emergency');
    }

    // ========================================
    // ACCESSORS — SWAHILI + UI
    // ========================================
    public function getTypeSwahiliAttribute(): string
    {
        return match ($this->appointment_type) {
            'Emergency' => 'Dharura',
            'Routine Checkup' => 'Ukaguzi wa Kawaida',
            'Vaccination' => 'Chanjo',
            'Surgery' => 'Upasuaji',
            'Follow-up' => 'Kurudia',
            'Consultation' => 'Ushauri',
            default => $this->appointment_type
        };
    }

    public function getStatusSwahiliAttribute(): string
    {
        return match ($this->status) {
            'Scheduled' => 'Imepangwa',
            'Confirmed' => 'Imethibitishwa',
            'In Progress' => 'Inaendelea',
            'Completed' => 'Imekamilika',
            'Cancelled' => 'Imeghairiwa',
            'No Show' => 'Hajafika',
            default => $this->status
        };
    }

    public function getFullDateTimeAttribute(): string
    {
        return $this->appointment_date->format('d/m/Y') .
               ($this->appointment_time ? ' - ' . $this->appointment_time->format('H:i') : '');
    }

    public function getLocationTextAttribute(): string
    {
        if ($this->location_type === 'Clinic Visit') {
            return $this->veterinarian?->clinic_name ?? 'Kliniki';
        }
        return $this->location?->full_address ?? 'Shamba la Mkulima';
    }

    public function getGoogleMapsUrlAttribute(): ?string
    {
        if ($this->location) {
            return $this->location->google_maps_url;
        }
        return null;
    }

    public function getSmsReminderAttribute(): string
    {
        $vet = $this->veterinarian?->user->fullname ?? 'Daktari';
        $date = $this->appointment_date->format('d/m/Y');
        $time = $this->appointment_time?->format('H:i') ?? 'saa 9:00';
        $animal = $this->animal?->tag ?? 'ng’ombe wako';
        return "UKUMBUKA: $vet atakuja kukuona $date saa $time kwa $this->type_swahili. $animal: $this->location_text. Asante!";
    }

    public function getDurationTextAttribute(): string
    {
        $mins = $this->estimated_duration_minutes ?? 60;
        return $mins . ' dakika';
    }

    public function getCheckInQrAttribute(): string
    {
        $url = route('appointment.checkin', $this->appointment_id);
        return "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($url);
    }
}
