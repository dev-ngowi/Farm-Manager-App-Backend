<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PregnancyCheck extends Model
{
    protected $primaryKey = 'check_id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'breeding_id',
        'vet_id',
        'check_date',
        'method',
        'result',
        'expected_delivery_date',
        'fetus_count',
        'notes',
    ];

    protected $casts = [
        'check_date' => 'date',
        'expected_delivery_date' => 'date',
        'fetus_count' => 'integer',
        'created_at' => 'datetime',
    ];

    // =================================================================
    // RELATIONSHIPS
    // =================================================================

    public function breeding(): BelongsTo
    {
        return $this->belongsTo(BreedingRecord::class, 'breeding_id', 'breeding_id');
    }

    public function vet(): BelongsTo
    {
        return $this->belongsTo(Veterinarian::class, 'vet_id', 'id');
    }

    public function dam()
    {
        return $this->breeding()->first()?->dam();
    }

    public function sire()
    {
        return $this->breeding()->first()?->sire();
    }

    // =================================================================
    // ACCESSORS
    // =================================================================

    public function getDaysAfterBreedingAttribute(): int
    {
        return $this->breeding?->breeding_date
            ? $this->breeding->breeding_date->diffInDays($this->check_date)
            : 0;
    }

    public function getIsAccurateAttribute(): ?bool
    {
        if (!$this->breeding?->birthRecord) return null;
        $actual = $this->breeding->birthRecord->actual_delivery_date;
        $predicted = $this->expected_delivery_date;
        if (!$predicted) return null;
        $diff = abs($actual->diffInDays($predicted));
        return $diff <= 7; // ±7 days = accurate
    }

    public function getAccuracyGradeAttribute(): string
    {
        if ($this->is_accurate === null) return 'Pending';
        return $this->is_accurate ? 'Accurate' : 'Inaccurate';
    }

    public function getFetusPredictionAttribute(): string
    {
        return match ($this->fetus_count) {
            1 => 'Single',
            2 => 'Twins',
            3 => 'Triplets',
            4 => 'Quadruplets',
            default => 'Unknown',
        };
    }

    // =================================================================
    // SCOPES
    // =================================================================

    public function scopePregnant($query)
    {
        return $query->where('result', 'Pregnant');
    }

    public function scopeNotPregnant($query)
    {
        return $query->where('result', 'Not Pregnant');
    }

    public function scopeUltrasound($query)
    {
        return $query->where('method', 'Ultrasound');
    }

    public function scopeAccurate($query)
    {
        return $query->whereHas('breeding.birthRecord', function ($q) {
            $q->whereRaw('ABS(DATEDIFF(birth_records.actual_delivery_date, pregnancy_checks.expected_delivery_date)) <= 7');
        });
    }

    public function scopeRecent($query, $days = 30)
    {
        return $query->where('check_date', '>=', now()->subDays($days));
    }

    public function scopeByVet($query, $vetId)
    {
        return $query->where('vet_id', $vetId);
    }

    public function scopeTwinsDetected($query)
    {
        return $query->where('fetus_count', '>=', 2);
    }
}