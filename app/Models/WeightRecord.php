<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Carbon\Carbon;

class WeightRecord extends Model
{
    // =================================================================
    // TABLE CONFIGURATION
    // =================================================================

    protected $table = 'weight_records';
    protected $primaryKey = 'weight_id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = true;

    protected $fillable = [
        'animal_id',
        'record_date',
        'weight_kg',
        'body_condition_score',    // 1.0 - 5.0
        'measurement_method',      // Scale, Tape, Visual
        'heart_girth_cm',
        'height_cm',
        'recorded_by',
        'location',
        'notes',
    ];

    protected $casts = [
        'record_date'          => 'date',
        'weight_kg'            => 'decimal:2',
        'body_condition_score' => 'decimal:1',
        'heart_girth_cm'       => 'decimal:1',
        'height_cm'            => 'decimal:1',
        'created_at'           => 'datetime',
        'updated_at'           => 'datetime',
    ];

    // =================================================================
    // CORE RELATIONSHIPS
    // =================================================================

    public function animal(): BelongsTo
    {
        return $this->belongsTo(Livestock::class, 'animal_id', 'animal_id');
    }

    public function species()
    {
        return $this->animal()->first()?->species();
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

    // =================================================================
    // DYNAMIC ACCESSORS (REAL-TIME KPIs)
    // =================================================================

    public function previous(): ?WeightRecord
    {
        return $this->animal->weightRecords()
            ->where('record_date', '<', $this->record_date)
            ->latest('record_date')
            ->first();
    }

    public function getDaysSinceLastAttribute(): ?int
    {
        return $this->previous()?->record_date->diffInDays($this->record_date);
    }

    public function getAdgSinceLastAttribute(): ?float
    {
        if (!$this->previous() || $this->days_since_last == 0) return null;
        $gain = $this->weight_kg - $this->previous()->weight_kg;
        return round($gain / $this->days_since_last, 3);
    }

    public function getAdgLifetimeAttribute(): ?float
    {
        $birthWeight = $this->animal->weight_at_birth_kg 
            ?? $this->animal->weightRecords()->oldest('record_date')->first()?->weight_kg;
        if (!$birthWeight) return null;
        $days = $this->animal->date_of_birth?->diffInDays($this->record_date) ?: 1;
        return round(($this->weight_kg - $birthWeight) / $days, 3);
    }

    public function getFeedConsumedSinceLastAttribute(): ?float
    {
        if (!$this->previous()) return null;
        return $this->feedIntakes()
            ->whereBetween('intake_date', [
                $this->previous()->record_date,
                $this->record_date
            ])
            ->sum('quantity');
    }

    public function getFcrSinceLastAttribute(): ?float
    {
        $feed = $this->feed_consumed_since_last;
        $gain = $this->previous() ? ($this->weight_kg - $this->previous()->weight_kg) : 0;
        return $feed && $gain > 0 ? round($feed / $gain, 2) : null;
    }

    public function getCostOfGainAttribute(): ?float
    {
        $cost = $this->feedIntakes()
            ->whereBetween('intake_date', [
                $this->previous()?->record_date ?? $this->animal->date_of_birth,
                $this->record_date
            ])
            ->sum('cost');
        $gain = $this->previous() ? ($this->weight_kg - $this->previous()->weight_kg) : 0;
        return $gain > 0 ? round($cost / $gain, 2) : null;
    }

    public function getBcsGradeAttribute(): string
    {
        return match (true) {
            $this->body_condition_score <= 1.5 => 'Emaciated',
            $this->body_condition_score <= 2.5 => 'Thin',
            $this->body_condition_score <= 3.5 => 'Ideal',
            $this->body_condition_score <= 4.0 => 'Fat',
            default => 'Obese',
        };
    }

    public function getTargetWeightAttribute(): int
    {
        return match ($this->species?->species_name) {
            'Cattle' => 450,
            'Goat'   => 35,
            'Sheep'  => 40,
            'Pig'    => 90,
            'Chicken'=> 2,
            default  => 400,
        };
    }

    public function getIsMarketWeightAttribute(): bool
    {
        return $this->weight_kg >= $this->target_weight;
    }

    public function getDaysToMarketAttribute(): ?int
    {
        if ($this->is_market_weight) return 0;
        $needed = $this->target_weight - $this->weight_kg;
        $adg = $this->adg_since_last ?? 0.75;
        return $adg > 0 ? (int) ceil($needed / $adg) : null;
    }

    public function getProjectedSaleDateAttribute(): ?string
    {
        if ($this->days_to_market === null) return 'Unknown';
        if ($this->days_to_market === 0) return 'Ready Now';
        return now()->addDays($this->days_to_market)->format('d M Y');
    }

    public function getEstimatedPriceAttribute(): ?float
    {
        $pricePerKg = match ($this->species?->species_name) {
            'Cattle' => 480,
            'Goat'   => 520,
            'Sheep'  => 500,
            'Pig'    => 420,
            default  => 450,
        };
        return round($this->weight_kg * $pricePerKg, 2);
    }

    public function getProjectedProfitAttribute(): ?float
    {
        $totalCost = $this->animal->total_expenses + ($this->animal->purchase_cost ?? 0);
        return $this->estimated_price ? round($this->estimated_price - $totalCost, 2) : null;
    }

    public function getEstimatedFromTapeAttribute(): ?float
    {
        if (!$this->heart_girth_cm || $this->species?->species_name !== 'Cattle') return null;
        // Schaeffer formula: Weight (kg) = (HG² × Length) / 300
        $length = $this->height_cm ?? 140;
        return round((pow($this->heart_girth_cm, 2) * $length) / 300, 2);
    }

    public function getAccuracyPercentageAttribute(): ?float
    {
        if (!$this->estimated_from_tape) return null;
        $diff = abs($this->weight_kg - $this->estimated_from_tape);
        return round((1 - ($diff / $this->weight_kg)) * 100, 1);
    }

    // =================================================================
    // SCOPES & ALERTS
    // =================================================================

    public function scopeLatestFirst($query)
    {
        return $query->orderByDesc('record_date');
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('record_date', now()->month)
                     ->whereYear('record_date', now()->year);
    }

    public function scopeUnderweight($query, $threshold = 200)
    {
        return $query->where('weight_kg', '<', $threshold);
    }

    public function scopeAccurate($query)
    {
        return $query->where('measurement_method', 'Scale');
    }

    public function scopeTape($query)
    {
        return $query->where('measurement_method', 'Tape');
    }

    public function scopeLowBcs($query)
    {
        return $query->where('body_condition_score', '<', 2.5);
    }

    public function scopeHighBcs($query)
    {
        return $query->where('body_condition_score', '>', 4.0);
    }

    public function scopeMarketReady($query)
    {
        return $query->whereRaw('weight_kg >= (
            SELECT CASE 
                WHEN species.species_name = "Cattle" THEN 450
                WHEN species.species_name = "Goat" THEN 35
                WHEN species.species_name = "Sheep" THEN 40
                WHEN species.species_name = "Pig" THEN 90
                ELSE 400
            END
            FROM livestock 
            JOIN species ON livestock.species_id = species.species_id
            WHERE livestock.animal_id = weight_records.animal_id
        )');
    }

    public function scopeSlowGrowth($query, $adg = 0.6)
    {
        return $query->whereRaw('
            (weight_kg - (
                SELECT weight_kg FROM weight_records w2 
                WHERE w2.animal_id = weight_records.animal_id 
                  AND w2.record_date < weight_records.record_date 
                ORDER BY w2.record_date DESC LIMIT 1
            )) / DATEDIFF(weight_records.record_date, (
                SELECT record_date FROM weight_records w2 
                WHERE w2.animal_id = weight_records.animal_id 
                  AND w2.record_date < weight_records.record_date 
                ORDER BY w2.record_date DESC LIMIT 1
            )) < ?
        ', [$adg]);
    }

    public function scopeReadyToSell($query)
    {
        return $query->marketReady()
                     ->where('record_date', '>=', now()->subDays(30));
    }

    public function scopeNeedsWeighing($query)
    {
        return $query->whereRaw('
            weight_records.record_date = (
                SELECT MAX(record_date) 
                FROM weight_records w2 
                WHERE w2.animal_id = weight_records.animal_id
            ) 
            AND weight_records.record_date < DATE_SUB(CURDATE(), INTERVAL 60 DAY)
        ');
    }
}