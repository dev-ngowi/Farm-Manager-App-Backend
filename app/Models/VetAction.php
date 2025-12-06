<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VetAction extends Model
{
    use HasFactory;

    protected $table = 'vet_actions';
    protected $primaryKey = 'action_id';

    protected $fillable = [
        'health_id', 'diagnosis_id', 'vet_id', 'action_type',
        'action_date', 'action_time', 'action_location',

        // Immediate Action Details
        'medicine_name', 'dosage', 'administration_route',
        'vaccine_name', 'vaccine_batch_number', 'vaccination_date', // The dose given *today*

        // Planning and Cost
        'prescription_details', 'surgery_details',
        'treatment_cost', 'payment_status', 'notes',
        'advice_notes',
    ];

    protected $casts = [
        'action_date' => 'date',
        'action_time' => 'datetime:H:i',
        'vaccination_date' => 'date',
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

    public function recoveryRecords(): HasMany
    {
        return $this->hasMany(RecoveryRecord::class, 'action_id');
    }

    public function latestRecovery()
    {
        return $this->hasOne(RecoveryRecord::class, 'action_id')->latestOfMany();
    }

    public function prescription()
    {
        return $this->hasOne(Prescription::class, 'action_id');
    }

    /**
     * The future vaccination schedules planned as a result of this action.
     */
    public function vaccinationSchedules(): HasMany
    {
        return $this->hasMany(VaccinationSchedule::class, 'vet_action_id');
    }

    // ========================================
    // BOOT: Auto-update HealthReport status & generate future schedules
    // ========================================
    protected static function booted()
    {
        static::created(function ($action) {
            $report = $action->healthReport;
            if ($report) {
                $newStatus = match ($action->action_type) {
                    'Treatment', 'Surgery', 'Vaccination' => 'Under Treatment',
                    'Prescription'        => 'Awaiting Treatment',
                    'Advisory'            => 'Under Diagnosis',
                    default               => $report->status
                };
                $report->update(['status' => $newStatus]);
            }

            // Mark as Paid if cost is 0 or waived
            if ($action->treatment_cost <= 0 || $action->payment_status === 'Waived') {
                $action->update(['payment_status' => 'Waived']);
            }
        });
    }

    // ========================================
    // ACCESSORS (Unchanged or adapted)
    // ========================================

    public function isFullyRecovered(): bool
    {
        return $this->latestRecovery?->recovery_status === 'Fully Recovered';
    }

    // ... (All other scopes and accessors remain as they were) ...

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
}
