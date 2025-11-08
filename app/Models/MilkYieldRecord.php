<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Carbon\Carbon;

class MilkYieldRecord extends Model
{
    // =================================================================
    // TABLE CONFIGURATION
    // =================================================================

    protected $table = 'milk_yield_records';
    protected $primaryKey = 'yield_id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = true;

    protected $fillable = [
        'animal_id',
        'yield_date',
        'milking_session',     // Morning, Midday, Evening
        'quantity_liters',
        'quality_grade',       // A, B, C, Rejected
        'fat_content',
        'protein_content',
        'somatic_cell_count',
        'temperature',
        'conductivity',
        'collection_center',
        'collector_name',
        'notes',
    ];

       protected $casts = [
        'yield_date'         => 'date',
        'quantity_liters'    => 'decimal:2',
        'fat_content'        => 'decimal:2',
        'protein_content'    => 'decimal:2',
        'somatic_cell_count' => 'integer',
        'temperature'        => 'decimal:1',
        'conductivity'       => 'decimal:2',
        'created_at'         => 'datetime',
        'updated_at'         => 'datetime',
    ];

    // =================================================================
    // CORE RELATIONSHIPS
    // =================================================================

    public function animal(): BelongsTo
    {
        return $this->belongsTo(Livestock::class, 'animal_id', 'animal_id');
    }

    public function farmer()
    {
        return $this->animal()->first()?->farmer();
    }

    public function income(): HasOneThrough
    {
        return $this->hasOneThrough(
            Income::class,
            Livestock::class,
            'animal_id',
            'animal_id',
            'animal_id',
            'animal_id'
        )->whereRaw('DATE(income.income_date) = ?', [$this->yield_date])
         ->whereHas('category', fn($q) => $q->where('category_name', 'Milk Sale'));
    }

    public function feedIntakeToday(): HasOneThrough
    {
        return $this->hasOneThrough(
            FeedIntakeRecord::class,
            Livestock::class,
            'animal_id',
            'animal_id',
            'animal_id',
            'animal_id'
        )->where('feed_intake_records.intake_date', $this->yield_date);
    }

    // =================================================================
    // POWERFUL ACCESSORS (REAL-TIME KPIs)
    // =================================================================

    public function getIsMorningAttribute(): bool
    {
        return $this->milking_session === 'Morning';
    }

    public function getIsEveningAttribute(): bool
    {
        return $this->milking_session === 'Evening';
    }

    public function getIsHighSccAttribute(): bool
    {
        return $this->somatic_cell_count >= 400000;
    }

    public function getIsCriticalSccAttribute(): bool
    {
        return $this->somatic_cell_count >= 750000;
    }

    public function getSccGradeAttribute(): string
    {
        return match (true) {
            $this->somatic_cell_count < 200000 => 'Excellent',
            $this->somatic_cell_count < 400000 => 'Good',
            $this->somatic_cell_count < 750000 => 'Warning',
            default => 'Critical',
        };
    }

    public function getPaymentValueAttribute(): float
    {
        $basePrice = 45.00; // KES per liter (make configurable via settings table later)
        $bonus = match ($this->quality_grade) {
            'A' => 1.10,
            'B' => 1.00,
            'C' => 0.85,
            'Rejected' => 0.00,
            default => 1.00,
        };
        return round($this->quantity_liters * $basePrice * $bonus, 2);
    }

    public function getActualIncomeAttribute(): ?float
    {
        return $this->income?->amount;
    }

    public function getIncomeDifferenceAttribute(): ?float
    {
        if (!$this->actual_income) return null;
        return round($this->actual_income - $this->payment_value, 2);
    }

    public function getDaysInMilkAttribute(): ?int
    {
        $lastBirth = $this->animal->offspringAsDam()->latest('date_of_birth')->first()?->date_of_birth;
        return $lastBirth ? $lastBirth->diffInDays($this->yield_date) : null;
    }

    public function getPeakYieldAttribute(): ?float
    {
        return $this->animal->milkYields()
            ->where('yield_date', '>=', now()->subDays(120))
            ->max('quantity_liters');
    }

    public function getIsInPeakAttribute(): bool
    {
        return $this->quantity_liters >= ($this->peak_yield * 0.9);
    }

    public function getFeedCostTodayAttribute(): ?float
    {
        return $this->feedIntakeToday?->cost;
    }

    public function getProfitTodayAttribute(): ?float
    {
        if (!$this->feed_cost_today || !$this->actual_income) return null;
        return round($this->actual_income - $this->feed_cost_today, 2);
    }

    public function getFcrTodayAttribute(): ?float
    {
        $feed = $this->feedIntakeToday?->quantity;
        return $feed && $feed > 0 ? round($feed / $this->quantity_liters, 3) : null;
    }

    public function getEfficiencyGradeAttribute(): string
    {
        $fcr = $this->fcr_today;
        return match (true) {
            $fcr === null => 'No Feed Data',
            $fcr <= 0.8   => 'Excellent',
            $fcr <= 1.0   => 'Good',
            $fcr <= 1.3   => 'Average',
            default       => 'Poor',
        };
    }

    public function getSolidsContentAttribute(): ?float
    {
        if (!$this->fat_content || !$this->protein_content) return null;
        return round($this->fat_content + $this->protein_content, 2);
    }

    public function getIsMastitisRiskAttribute(): bool
    {
        return $this->is_high_scc || 
               ($this->conductivity && $this->conductivity > 6.5) ||
               ($this->temperature && $this->temperature > 39.5);
    }

    // =================================================================
    // SCOPES & ALERTS
    // =================================================================

    public function scopeToday($query)
    {
        return $query->where('yield_date', today());
    }

    public function scopeThisWeek($query)
    {
        return $query->whereBetween('yield_date', [
            now()->startOfWeek(),
            now()->endOfWeek()
        ]);
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('yield_date', now()->month)
                     ->whereYear('yield_date', now()->year);
    }

    public function scopeMorning($query)
    {
        return $query->where('milking_session', 'Morning');
    }

    public function scopeEvening($query)
    {
        return $query->where('milking_session', 'Evening');
    }

    public function scopeRejected($query)
    {
        return $query->where('quality_grade', 'Rejected');
    }

    public function scopeHighScc($query, $threshold = 400000)
    {
        return $query->where('somatic_cell_count', '>=', $threshold);
    }

    public function scopeCriticalScc($query)
    {
        return $query->where('somatic_cell_count', '>=', 750000);
    }

    public function scopeForCow($query, $animalId)
    {
        return $query->where('animal_id', $animalId);
    }

    public function scopeMastitisRisk($query)
    {
        return $query->where(function ($q) {
            $q->where('somatic_cell_count', '>=', 400000)
              ->orWhere('conductivity', '>', 6.5)
              ->orWhere('temperature', '>', 39.5);
        });
    }

    public function scopeLowYield($query, $threshold = 10.0)
    {
        return $query->where('quantity_liters', '<', $threshold);
    }

    public function scopeHighYield($query, $threshold = 30.0)
    {
        return $query->where('quantity_liters', '>=', $threshold);
    }

    public function scopePeakPerformers($query)
    {
        return $query->whereRaw('quantity_liters >= 0.9 * (
            SELECT MAX(quantity_liters) 
            FROM milk_yield_records my2 
            WHERE my2.animal_id = milk_yield_records.animal_id
        )');
    }

    public function scopeDeclining($query)
    {
        return $query->whereRaw('
            quantity_liters < (
                SELECT AVG(quantity_liters) 
                FROM milk_yield_records my2 
                WHERE my2.animal_id = milk_yield_records.animal_id 
                  AND my2.yield_date >= DATE_SUB(milk_yield_records.yield_date, INTERVAL 7 DAY)
            )
        ');
    }
}