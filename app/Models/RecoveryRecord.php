<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecoveryRecord extends Model
{
    use HasFactory;

    protected $table = 'recovery_records';
    protected $primaryKey = 'recovery_id';

    protected $fillable = [
        'action_id',
        'recovery_status',
        'recovery_date',
        'recovery_percentage',
        'recovery_notes',
        'reported_by',
    ];

    protected $casts = [
        'recovery_date' => 'date',
        'recovery_percentage' => 'integer',
    ];

    // ========================================
    // RELATIONSHIPS
    // ========================================
    public function vetAction(): BelongsTo
    {
        return $this->belongsTo(VetAction::class, 'action_id');
    }

    public function healthReport()
    {
        return $this->belongsToThrough(HealthReport::class, VetAction::class, 'action_id', 'health_id');
    }

    public function animal()
    {
        return $this->belongsToThrough(Livestock::class, [VetAction::class, HealthReport::class]);
    }

    // ========================================
    // BOOT: Auto-sync HealthReport status
    // ========================================
    protected static function booted()
    {
        static::saved(function ($record) {
            $report = $record->vetAction?->healthReport;
            if (!$report) return;

            $newStatus = match ($record->recovery_status) {
                'Fully Recovered' => 'Recovered',
                'Deceased'        => 'Deceased',
                'Worsened'        => 'Under Treatment',
                default           => 'Under Treatment'
            };

            $report->update(['status' => $newStatus]);
        });
    }

    // ========================================
    // SCOPES
    // ========================================
    public function scopeToday($query)
    {
        return $query->whereDate('recovery_date', today());
    }

    public function scopeByFarmer($query)
    {
        return $query->where('reported_by', 'Farmer');
    }

    public function scopeCritical($query)
    {
        return $query->whereIn('recovery_status', ['Worsened', 'Deceased']);
    }

    // ========================================
    // ACCESSORS
    // ========================================
    public function getStatusSwahiliAttribute(): string
    {
        return match ($this->recovery_status) {
            'Ongoing'         => 'Inaendelea',
            'Improved'        => 'Ameboreshwa',
            'Fully Recovered' => 'Amepona Kabisa',
            'No Change'       => 'Hakuna Mabadiliko',
            'Worsened'        => 'Amezidi Kuwa Mbaya',
            'Deceased'        => 'Amekufa',
            default           => $this->recovery_status
        };
    }

    public function getProgressBarClassAttribute(): string
    {
        $percent = $this->recovery_percentage ?? 0;
        if ($percent >= 90) return 'bg-green-500';
        if ($percent >= 70) return 'bg-lime-500';
        if ($percent >= 50) return 'bg-yellow-500';
        if ($percent >= 30) return 'bg-orange-500';
        return 'bg-red-500';
    }

    public function getReportedByBadgeAttribute(): string
    {
        return $this->reported_by === 'Farmer'
            ? 'bg-blue-100 text-blue-800'
            : 'bg-purple-100 text-purple-800';
    }

    public function getDaysSinceTreatmentAttribute(): ?int
    {
        if (!$this->recovery_date || !$this->vetAction?->action_date) return null;
        return $this->vetAction->action_date->diffInDays($this->recovery_date);
    }

    public function getTimelineTextAttribute(): string
    {
        $days = $this->days_since_treatment;
        if ($days === null) return '';
        if ($days == 0) return 'Leo';
        if ($days == 1) return 'Jana';
        return "Baada ya siku $days";
    }
}
