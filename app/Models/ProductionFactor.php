<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Carbon\Carbon;

class ProductionFactor extends Model
{
    // =================================================================
    // TABLE CONFIGURATION
    // =================================================================

    protected $table = 'production_factors';
    protected $primaryKey = 'factor_id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = true;

    protected $fillable = [
        'animal_id',
        'calculation_date',
        'period_start',
        'period_end',
        'total_feed_consumed_kg',
        'total_milk_produced_liters',
        'weight_gain_kg',
        'avg_daily_milk_liters',
        'feed_to_milk_ratio',
        'feed_conversion_ratio',
        'milk_per_kg_feed',
        'income_from_milk',
        'feed_cost',
        'other_costs',
        'net_profit',
        'profit_per_day',
        'efficiency_grade',
        'notes',
    ];

    protected $casts = [
        'calculation_date'         => 'date',
        'period_start'             => 'date',
        'period_end'               => 'date',
        'total_feed_consumed_kg'   => 'decimal:2',
        'total_milk_produced_liters' => 'decimal:2',
        'weight_gain_kg'           => 'decimal:2',
        'avg_daily_milk_liters'    => 'decimal:2',
        'feed_to_milk_ratio'       => 'decimal:3',
        'feed_conversion_ratio'    => 'decimal:2',
        'milk_per_kg_feed'         => 'decimal:3',
        'income_from_milk'         => 'decimal:2',
        'feed_cost'                => 'decimal:2',
        'other_costs'              => 'decimal:2',
        'net_profit'               => 'decimal:2',
        'profit_per_day'           => 'decimal:2',
        'created_at'               => 'datetime',
        'updated_at'               => 'datetime',
    ];

    // =================================================================
    // CORE RELATIONSHIPS
    // =================================================================

    public function animal(): BelongsTo
    {
        return $this->belongsTo(Livestock::class, 'animal_id', 'animal_id');
    }

    public function milkYields(): HasManyThrough
    {
        return $this->hasManyThrough(
            MilkYieldRecord::class,
            Livestock::class,
            'animal_id',
            'animal_id',
            'animal_id',
            'animal_id'
        );
    }

    public function feedIntakes(): HasManyThrough
    {
        return $this->hasManyThrough(
            FeedIntakeRecord::class,
            Livestock::class,
            'animal_id',
            'animal_id',
            'animal_id',
            'animal_id'
        );
    }

    public function weightRecords(): HasManyThrough
    {
        return $this->hasManyThrough(
            WeightRecord::class,
            Livestock::class,
            'animal_id',
            'animal_id',
            'animal_id',
            'animal_id'
        );
    }

    // =================================================================
    // AUTO-CALCULATED ACCESSORS (REAL-TIME KPIs)
    // =================================================================

    public function getDaysInPeriodAttribute(): int
    {
        return $this->period_start->diffInDays($this->period_end) + 1;
    }

    public function getAvgDailyMilkLitersAttribute(): ?float
    {
        return $this->days_in_period > 0 
            ? round($this->total_milk_produced_liters / $this->days_in_period, 2)
            : null;
    }

    public function getFeedToMilkRatioAttribute(): ?float
    {
        return $this->total_milk_produced_liters > 0
            ? round($this->total_feed_consumed_kg / $this->total_milk_produced_liters, 3)
            : null;
    }

    public function getFeedConversionRatioAttribute(): ?float
    {
        return $this->weight_gain_kg > 0
            ? round($this->total_feed_consumed_kg / $this->weight_gain_kg, 2)
            : null;
    }

    public function getMilkPerKgFeedAttribute(): ?float
    {
        return $this->total_feed_consumed_kg > 0
            ? round($this->total_milk_produced_liters / $this->total_feed_consumed_kg, 3)
            : null;
    }

    public function getIncomeFromMilkAttribute(): ?float
    {
        return $this->milkYields()
            ->whereBetween('yield_date', [$this->period_start, $this->period_end])
            ->sum('payment_value');
    }

    public function getFeedCostAttribute(): ?float
    {
        return $this->feedIntakes()
            ->whereBetween('intake_date', [$this->period_start, $this->period_end])
            ->sum('cost');
    }

    public function getOtherCostsAttribute(): ?float
    {
        return $this->animal->expenses()
            ->whereBetween('expense_date', [$this->period_start, $this->period_end])
            ->where('category', '!=', 'Feed')
            ->sum('amount');
    }

    public function getTotalCostAttribute(): ?float
    {
        return ($this->feed_cost ?? 0) + ($this->other_costs ?? 0);
    }

    public function getNetProfitAttribute(): ?float
    {
        return ($this->income_from_milk ?? 0) - ($this->total_cost ?? 0);
    }

    public function getProfitPerDayAttribute(): ?float
    {
        return $this->days_in_period > 0 
            ? round($this->net_profit / $this->days_in_period, 2)
            : null;
    }

    public function getEfficiencyGradeAttribute(): string
    {
        $fcr = $this->feed_to_milk_ratio;
        return match (true) {
            $fcr === null => 'No Data',
            $fcr <= 1.0   => 'World Class',
            $fcr <= 1.2   => 'Excellent',
            $fcr <= 1.5   => 'Good',
            $fcr <= 2.0   => 'Average',
            default       => 'Poor',
        };
    }

    public function getIsTopPerformerAttribute(): bool
    {
        return $this->feed_to_milk_ratio <= 1.3 
            && $this->profit_per_day >= 300 
            && $this->avg_daily_milk_liters >= 25;
    }

    public function getIsLossMakingAttribute(): bool
    {
        return $this->profit_per_day < 0;
    }

    public function getCullingRecommendationAttribute(): string
    {
        if ($this->is_loss_making && $this->animal->age_in_years > 6) {
            return 'CULL - Chronic Loss Maker';
        }
        if ($this->feed_to_milk_ratio > 2.5) {
            return 'CULL - Poor Efficiency';
        }
        if ($this->animal->has_mastitis_risk) {
            return 'TREAT or CULL';
        }
        return 'KEEP';
    }

    public function getBonusEligibleAttribute(): bool
    {
        return $this->is_top_performer && $this->animal->status === 'Active';
    }

    public function getGeneticValueAttribute(): string
    {
        $rank = ProductionFactor::thisMonth()
            ->forDairyCows()
            ->orderByDesc('profit_per_day')
            ->pluck('animal_id')
            ->search($this->animal_id);

        $total = ProductionFactor::thisMonth()->forDairyCows()->count();
        $percentile = $total > 0 ? round((($total - $rank) / $total) * 100, 1) : 0;

        return match (true) {
            $percentile >= 90 => 'Elite',
            $percentile >= 75 => 'Superior',
            $percentile >= 50 => 'Good',
            default => 'Average',
        };
    }

    // =================================================================
    // SCOPES & ALERTS
    // =================================================================

    public function scopeThisMonth($query)
    {
        return $query->where('period_start', '>=', now()->startOfMonth())
                     ->where('period_end', '<=', now()->endOfMonth());
    }

    public function scopeLastMonth($query)
    {
        return $query->where('period_start', '>=', now()->subMonth()->startOfMonth())
                     ->where('period_end', '<=', now()->subMonth()->endOfMonth());
    }

    public function scopeThisYear($query)
    {
        return $query->whereYear('period_start', now()->year);
    }

    public function scopeExcellentEfficiency($query)
    {
        return $query->where('feed_to_milk_ratio', '<=', 1.3);
    }

    public function scopePoorPerformers($query)
    {
        return $query->where(function ($q) {
            $q->where('feed_to_milk_ratio', '>', 2.0)
              ->orWhere('profit_per_day', '<', 0);
        });
    }

    public function scopeLossMakers($query)
    {
        return $query->where('net_profit', '<', 0);
    }

    public function scopeTopPerformers($query)
    {
        return $query->where('profit_per_day', '>=', 500)
                     ->orWhere('feed_to_milk_ratio', '<=', 1.1);
    }

    public function scopeForDairyCows($query)
    {
        return $query->whereHas('animal', fn($q) =>
            $q->where('species_id', 1)->where('sex', 'Female')->where('status', 'Active')
        );
    }

    public function scopeBonusEligible($query)
    {
        return $query->where('profit_per_day', '>=', 400)
                     ->where('feed_to_milk_ratio', '<=', 1.4);
    }

    public function scopeCullingCandidates($query)
    {
        return $query->whereHas('animal', fn($q) =>
            $q->where('status', 'Active')
              ->whereRaw('TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) > 6')
        )->where(function ($q) {
            $q->where('profit_per_day', '<', -100)
              ->orWhere('feed_to_milk_ratio', '>', 2.5);
        });
    }
}