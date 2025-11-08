<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Carbon\Carbon;

class FeedIntakeRecord extends Model
{
    // =================================================================
    // TABLE CONFIGURATION
    // =================================================================

    protected $table = 'feed_intake_records';
    protected $primaryKey = 'intake_id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = true;

    protected $fillable = [
        'animal_id',
        'feed_id',
        'stock_id',           // from FeedStock batch
        'intake_date',
        'feeding_time',       // Morning, Midday, Evening
        'quantity',
        'cost_per_unit_used', // actual cost from batch
        'notes',
    ];

    protected $casts = [
        'intake_date'        => 'date',
        'feeding_time'       => 'string',
        'quantity'           => 'decimal:2',
        'cost_per_unit_used' => 'decimal:2',
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

    public function feed(): BelongsTo
    {
        return $this->belongsTo(FeedInventory::class, 'feed_id', 'feed_id');
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(FeedStock::class, 'stock_id', 'stock_id');
    }

    public function farmer()
    {
        return $this->animal()->first()?->farmer();
    }

    public function milkYield(): HasOneThrough
    {
        return $this->hasOneThrough(
            MilkYieldRecord::class,
            Livestock::class,
            'animal_id',
            'animal_id',
            'animal_id',
            'animal_id'
        )->whereDate('milk_yield_records.yield_date', $this->intake_date);
    }

    // =================================================================
    // DYNAMIC ACCESSORS (REAL-TIME KPIs)
    // =================================================================

    public function getCostAttribute(): float
    {
        return round($this->quantity * ($this->cost_per_unit_used ?? $this->feed?->average_cost_per_kg ?? 0), 2);
    }

    public function getProteinIntakeAttribute(): float
    {
        $protein = $this->feed?->protein_percentage ?? 0;
        return round(($this->quantity * $protein) / 100, 2); // kg → grams
    }

    public function getEnergyIntakeAttribute(): float
    {
        $energy = $this->feed?->energy_content_mj ?? 0;
        return round($this->quantity * $energy, 2); // MJ
    }

    public function getMilkProducedAttribute(): ?float
    {
        return $this->milkYield?->quantity_liters;
    }

    public function getFcrAttribute(): ?float
    {
        $milk = $this->milk_produced;
        return $milk && $milk > 0 ? round($this->quantity / $milk, 3) : null;
    }

    public function getCostPerLiterAttribute(): ?float
    {
        $milk = $this->milk_produced;
        return $milk && $milk > 0 ? round($this->cost / $milk, 2) : null;
    }

    public function getProfitPerDayAttribute(): ?float
    {
        $income = $this->animal?->income()
            ->where('income_date', $this->intake_date)
            ->sum('amount') ?? 0;
        return round($income - $this->cost, 2);
    }

    public function getIsMorningFeedAttribute(): bool
    {
        return $this->feeding_time === 'Morning';
    }

    public function getIsEveningFeedAttribute(): bool
    {
        return $this->feeding_time === 'Evening';
    }

    public function getEfficiencyGradeAttribute(): string
    {
        $fcr = $this->fcr;
        return match (true) {
            $fcr === null => 'No Milk',
            $fcr <= 0.8   => 'Excellent',
            $fcr <= 1.0   => 'Good',
            $fcr <= 1.3   => 'Average',
            default       => 'Poor',
        };
    }

    // =================================================================
    // SCOPES & ALERTS
    // =================================================================

    public function scopeToday($query)
    {
        return $query->where('intake_date', today());
    }

    public function scopeThisWeek($query)
    {
        return $query->whereBetween('intake_date', [
            now()->startOfWeek(),
            now()->endOfWeek()
        ]);
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('intake_date', now()->month)
                     ->whereYear('intake_date', now()->year);
    }

    public function scopeForAnimal($query, $animalId)
    {
        return $query->where('animal_id', $animalId);
    }

    public function scopeByFeed($query, $feedId)
    {
        return $query->where('feed_id', $feedId);
    }

    public function scopeByFeedType($query, $type)
    {
        return $query->whereHas('feed', fn($q) => $q->where('feed_type', $type));
    }

    public function scopeMorning($query)
    {
        return $query->where('feeding_time', 'Morning');
    }

    public function scopeEvening($query)
    {
        return $query->where('feeding_time', 'Evening');
    }

    public function scopeHighCost($query, $kesPerDay = 200)
    {
        return $query->whereRaw('quantity * cost_per_unit_used >= ?', [$kesPerDay]);
    }

    public function scopeLowEfficiency($query, $fcrThreshold = 1.5)
    {
        return $query->whereHas('milkYield', fn($q) => 
            $q->whereRaw('feed_intake_records.quantity / milk_yield_records.quantity_liters >= ?', [$fcrThreshold])
        );
    }

    public function scopeProfitable($query)
    {
        return $query->whereHas('animal.income', fn($q) => 
            $q->whereRaw('income.amount > feed_intake_records.quantity * feed_intake_records.cost_per_unit_used')
              ->whereDate('income.income_date', '=', \DB::raw('feed_intake_records.intake_date'))
        );
    }

    public function scopeMorningBetter($query)
    {
        return $query->whereRaw('
            (SELECT SUM(quantity_liters) 
             FROM milk_yield_records 
             WHERE animal_id = feed_intake_records.animal_id 
               AND yield_date = feed_intake_records.intake_date 
               AND milking_time = "Morning") 
            > 
            (SELECT SUM(quantity_liters) 
             FROM milk_yield_records 
             WHERE animal_id = feed_intake_records.animal_id 
               AND yield_date = feed_intake_records.intake_date 
               AND milking_time = "Evening")
        ');
    }
}