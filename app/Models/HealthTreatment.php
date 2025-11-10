<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class HealthTreatment extends Model
{
    use HasFactory;

    protected $table = 'health_treatments';
    protected $primaryKey = 'treatment_id';
    public $timestamps = true;

    protected $fillable = [
        'diagnosis_id',
        'health_id',
        'treatment_date',
        'drug_name',
        'dosage',
        'route',
        'frequency',
        'duration_days',
        'administered_by',
        'cost',
        'outcome',
        'follow_up_date',
        'notes',
    ];

    protected $casts = [
        'treatment_date' => 'date',
        'follow_up_date' => 'date',
        'cost' => 'decimal:2',
    ];

    // ========================================
    // RELATIONSHIPS
    // ========================================
    public function healthReport(): BelongsTo
    {
        return $this->belongsTo(HealthReport::class, 'health_id', 'health_id');
    }

    public function diagnosis(): BelongsTo
    {
        return $this->belongsTo(HealthDiagnosis::class, 'diagnosis_id');
    }

    public function animal(): BelongsTo
    {
        return $this->healthReport()->first()?->animal();
    }

    // ========================================
    // ACCESSORS
    // ========================================
    public function getIsOngoingAttribute(): bool
    {
        return $this->outcome === null || $this->outcome === 'In Progress';
    }

    public function getDaysSinceTreatmentAttribute(): int
    {
        return $this->treatment_date ? $this->treatment_date->diffInDays(now()) : 0;
    }

    public function getFollowUpOverdueAttribute(): bool
    {
        return $this->follow_up_date && $this->follow_up_date->isPast() && $this->is_ongoing;
    }

    public function getRouteLabelAttribute(): string
    {
        return match ($this->route) {
            'IM' => 'Intramuscular',
            'IV' => 'Intravenous',
            'SC' => 'Subcutaneous',
            'Oral' => 'Oral',
            'Topical' => 'Topical',
            default => $this->route,
        };
    }

    // ========================================
    // SCOPES
    // ========================================
    public function scopeOngoing($query)
    {
        return $query->whereIn('outcome', [null, 'In Progress']);
    }

    public function scopeOverdueFollowUp($query)
    {
        return $query->where('follow_up_date', '<', now())
                     ->whereNull('outcome');
    }

    public function scopeByDrug($query, $drug)
    {
        return $query->where('drug_name', 'LIKE', "%{$drug}%");
    }

    public function scopeExpensive($query, $amount = 50000)
    {
        return $query->where('cost', '>', $amount);
    }
}
