<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class HealthDiagnosis extends Model
{
    use HasFactory;

    protected $table = 'health_diagnoses';
    protected $primaryKey = 'diagnosis_id';
    public $timestamps = true;

    protected $fillable = [
        'health_id',
        'vet_id',
        'diagnosis_date',
        'disease_condition',
        'diagnosis_method',
        'confidence_level',
        'lab_results',
        'notes',
        'is_confirmed',
    ];

    protected $casts = [
        'diagnosis_date' => 'date',
        'is_confirmed' => 'boolean',
        'lab_results' => 'array',
    ];

    // ========================================
    // RELATIONSHIPS
    // ========================================
    public function healthReport(): BelongsTo
    {
        return $this->belongsTo(HealthReport::class, 'health_id', 'health_id');
    }

    public function vet(): BelongsTo
    {
        return $this->belongsTo(Veterinarian::class, 'vet_id');
    }

    public function animal(): BelongsTo
    {
        return $this->healthReport()->first()?->animal();
    }

    public function treatments(): HasMany
    {
        return $this->hasMany(HealthTreatment::class, 'diagnosis_id');
    }

    // ========================================
    // ACCESSORS
    // ========================================
    public function getDiagnosisAgeAttribute(): int
    {
        return $this->diagnosis_date ? $this->diagnosis_date->diffInDays(now()) : 0;
    }

    public function getIsRecentAttribute(): bool
    {
        return $this->diagnosis_age <= 7;
    }

    public function getConfidenceBadgeAttribute(): string
    {
        return match ($this->confidence_level) {
            'High' => 'bg-green-100 text-green-800',
            'Medium' => 'bg-yellow-100 text-yellow-800',
            'Low' => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    // ========================================
    // SCOPES
    // ========================================
    public function scopeConfirmed($query)
    {
        return $query->where('is_confirmed', true);
    }

    public function scopeByDisease($query, $disease)
    {
        return $query->where('disease_condition', 'LIKE', "%{$disease}%");
    }

    public function scopeRecent($query)
    {
        return $query->where('diagnosis_date', '>=', now()->subDays(30));
    }

    public function scopeByVet($query, $vetId)
    {
        return $query->where('vet_id', $vetId);
    }
}
