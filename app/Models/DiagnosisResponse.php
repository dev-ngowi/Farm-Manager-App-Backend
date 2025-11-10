<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class DiagnosisResponse extends Model
{
    use HasFactory;

    protected $table = 'diagnosis_responses';
    protected $primaryKey = 'diagnosis_id';

    protected $fillable = [
        'health_id',
        'vet_id',
        'suspected_disease',
        'diagnosis_notes',
        'recommended_tests',
        'prognosis',
        'estimated_recovery_days',
        'diagnosis_date',
        'follow_up_required',
        'follow_up_date',
    ];

    protected $casts = [
        'diagnosis_date' => 'date',
        'follow_up_date' => 'date',
        'follow_up_required' => 'boolean',
        'estimated_recovery_days' => 'integer',
    ];

    // ========================================
    // RELATIONSHIPS
    // ========================================
    public function healthReport(): BelongsTo
    {
        return $this->belongsTo(HealthReport::class, 'health_id');
    }

    public function veterinarian(): BelongsTo
    {
        return $this->belongsTo(Veterinarian::class, 'vet_id');
    }

    // ========================================
    // BOOT: Auto-update HealthReport status
    // ========================================
    protected static function booted()
    {
        static::created(function ($diagnosis) {
            $report = $diagnosis->healthReport;
            if ($report) {
                $report->update([
                    'status' => 'Under Diagnosis',
                    'priority' => $diagnosis->prognosis === 'Grave' ? 'Emergency' : $report->priority,
                ]);
            }
        });

        static::updated(function ($diagnosis) {
            if ($diagnosis->follow_up_required && !$diagnosis->follow_up_date) {
                $diagnosis->follow_up_date = now()->addDays(7);
                $diagnosis->saveQuietly();
            }
        });
    }

    // ========================================
    // SCOPES
    // ========================================
    public function scopeRecent($query)
    {
        return $query->where('diagnosis_date', '>=', now()->subDays(30));
    }

    public function scopeGrave($query)
    {
        return $query->where('prognosis', 'Grave');
    }

    public function scopeNeedsFollowUp($query)
    {
        return $query->where('follow_up_required', true)
                     ->where('follow_up_date', '>', now());
    }

    // ========================================
    // ACCESSORS
    // ========================================
    public function getPrognosisColorAttribute(): string
    {
        return match ($this->prognosis) {
            'Excellent' => 'bg-green-100 text-green-800',
            'Good'      => 'bg-blue-100 text-blue-800',
            'Fair'      => 'bg-yellow-100 text-yellow-800',
            'Poor'      => 'bg-orange-100 text-orange-800',
            'Grave'     => 'bg-red-100 text-red-800',
            default     => 'bg-gray-100 text-gray-800',
        };
    }

    public function getRecoveryTextAttribute(): string
    {
        if (!$this->estimated_recovery_days) return 'Unknown';
        return $this->estimated_recovery_days . ' day' . ($this->estimated_recovery_days > 1 ? 's' : '');
    }

    public function getSwahiliDiseaseAttribute(): string
    {
        $map = [
            'Foot and Mouth Disease' => 'Magonjwa ya Miguu na Mdomo',
            'Brucellosis' => 'Homa ya Brucella',
            'Anthrax' => 'Kimeta',
            'Lumpy Skin Disease' => 'Magonjwa ya Ngozi',
            'Mastitis' => 'Matiti Kuvimba',
            'East Coast Fever' => 'Homa ya Pembe za Pembe',
        ];
        return $map[$this->suspected_disease] ?? $this->suspected_disease;
    }
}
