<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VetAction extends Model
{
    use HasFactory;

    protected $table = 'vet_actions';
    protected $primaryKey = 'action_id';

    protected $fillable = [
        'health_id', 'diagnosis_id', 'vet_id', 'action_type',
        'action_date', 'action_time', 'action_location',
        'medicine_name', 'dosage', 'administration_route',
        'advice_notes', 'vaccine_name', 'vaccine_batch_number',
        'vaccination_date', 'next_vaccination_due',
        'prescription_details', 'surgery_details',
        'treatment_cost', 'payment_status', 'notes'
    ];

    protected $casts = [
        'action_date' => 'date',
        'action_time' => 'datetime:H:i',
        'vaccination_date' => 'date',
        'next_vaccination_due' => 'date',
        'treatment_cost' => 'decimal:2',
    ];

    // ========================================
    // RELATIONSHIPS
    // ========================================
    public function healthReport(): BelongsTo
    {
        return $this->belongsTo(HealthReport::class, 'health_id');
    }

    public function diagnosis(): BelongsTo
    {
        return $this->belongsTo(DiagnosisResponse::class, 'diagnosis_id');
    }

    public function veterinarian(): BelongsTo
    {
        return $this->belongsTo(Veterinarian::class, 'vet_id');
    }

    // In app/Models/VetAction.php

public function recoveryRecords()
{
    return $this->hasMany(RecoveryRecord::class, 'action_id');
}

public function latestRecovery()
{
    return $this->hasOne(RecoveryRecord::class, 'action_id')->latestOfMany();
}

public function isFullyRecovered(): bool
{
    return $this->latestRecovery?->recovery_status === 'Fully Recovered';
}

// In app/Models/VetAction.php
public function prescription()
{
    return $this->hasOne(Prescription::class, 'action_id');
}

    // ========================================
    // BOOT: Auto-update HealthReport status
    // ========================================
    protected static function booted()
    {
        static::created(function ($action) {
            $report = $action->healthReport;
            if (!$report) return;

            $newStatus = match ($action->action_type) {
                'Treatment', 'Surgery' => 'Under Treatment',
                'Prescription'        => 'Awaiting Treatment',
                'Vaccination'         => 'Under Treatment',
                'Advisory'            => 'Under Diagnosis',
                default               => $report->status
            };

            $report->update(['status' => $newStatus]);

            // Mark as Paid if cost is 0 or waived
            if ($action->treatment_cost <= 0 || $action->payment_status === 'Waived') {
                $action->update(['payment_status' => 'Waived']);
            }
        });
    }

    // ========================================
    // SCOPES
    // ========================================
    public function scopeToday($query)
    {
        return $query->whereDate('action_date', today());
    }

    public function scopeUnpaid($query)
    {
        return $query->where('payment_status', 'Pending');
    }

    public function scopeFarmVisits($query)
    {
        return $query->where('action_location', 'Farm Visit');
    }

    // ========================================
    // ACCESSORS
    // ========================================
    public function getActionTypeSwahiliAttribute(): string
    {
        return match ($this->action_type) {
            'Treatment'     => 'Matibabu',
            'Advisory'      => 'Ushauri',
            'Vaccination'   => 'Chanjo',
            'Prescription'  => 'Dawa ya Kununua',
            'Surgery'       => 'Upasuaji',
            'Consultation'  => 'Ushauri wa Simu',
            default         => $this->action_type
        };
    }

    public function getLocationBadgeAttribute(): string
    {
        return match ($this->action_location) {
            'Farm Visit'         => 'bg-green-100 text-green-800',
            'Clinic'             => 'bg-blue-100 text-blue-800',
            'Remote Consultation'=> 'bg-purple-100 text-purple-800',
            default              => 'bg-gray-100'
        };
    }

    public function getCostFormattedAttribute(): string
    {
        if (!$this->treatment_cost) return 'Bure';
        return 'TZS ' . number_format($this->treatment_cost);
    }

    public function getNextVaccinationTextAttribute(): ?string
    {
        if (!$this->next_vaccination_due) return null;
        $days = now()->diffInDays($this->next_vaccination_due, false);
        if ($days < 0) return "Imepitwa kwa " . abs($days) . " siku";
        if ($days == 0) return "Leo";
        return "Baada ya siku $days";
    }
}
