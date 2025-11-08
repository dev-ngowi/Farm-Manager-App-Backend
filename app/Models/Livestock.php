<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Carbon\Carbon;

class Livestock extends Model
{
    // =================================================================
    // TABLE CONFIGURATION
    // =================================================================

    protected $table = 'livestock';
    protected $primaryKey = 'animal_id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = true;

    protected $fillable = [
        'farmer_id',
        'species_id',
        'breed_id',
        'tag_number',
        'name',
        'sex',
        'date_of_birth',
        'weight_at_birth_kg',
        'current_weight_kg',
        'sire_id',
        'dam_id',
        'purchase_date',
        'purchase_cost',
        'source',
        'status',
        'disposal_date',
        'disposal_reason',
        'notes',
    ];

    protected $casts = [
        'date_of_birth'     => 'date',
        'purchase_date'     => 'date',
        'disposal_date'     => 'date',
        'weight_at_birth_kg'=> 'decimal:2',
        'current_weight_kg' => 'decimal:2',
        'purchase_cost'     => 'decimal:2',
        'created_at'        => 'datetime',
        'updated_at'        => 'datetime',
    ];

    // =================================================================
    // CORE RELATIONSHIPS
    // =================================================================

    public function farmer(): BelongsTo
    {
        return $this->belongsTo(Farmer::class, 'farmer_id');
    }

    public function species(): BelongsTo
    {
        return $this->belongsTo(Species::class, 'species_id', 'species_id');
    }

    public function breed(): BelongsTo
    {
        return $this->belongsTo(Breed::class, 'breed_id', 'id');
    }

    public function sire(): BelongsTo
    {
        return $this->belongsTo(Livestock::class, 'sire_id', 'animal_id')->withDefault();
    }

    public function dam(): BelongsTo
    {
        return $this->belongsTo(Livestock::class, 'dam_id', 'animal_id')->withDefault();
    }

    public function offspringAsSire(): HasMany
    {
        return $this->hasMany(Livestock::class, 'sire_id', 'animal_id');
    }

    public function offspringAsDam(): HasMany
    {
        return $this->hasMany(Livestock::class, 'dam_id', 'animal_id');
    }

    public function offspring(): HasMany
    {
        return $this->offspringAsDam()->orWhere->offspringAsSire();
    }

    // =================================================================
    // BREEDING & REPRODUCTION
    // =================================================================

    public function breedingAsDam(): HasMany
    {
        return $this->hasMany(BreedingRecord::class, 'dam_id', 'animal_id');
    }

    public function breedingAsSire(): HasMany
    {
        return $this->hasMany(BreedingRecord::class, 'sire_id', 'animal_id');
    }

    public function birthRecord(): HasOne
    {
        return $this->hasOne(BirthRecord::class, 'breeding_id', 'breeding_id')
                    ->join('breeding_records', function ($join) {
                        $join->on('birth_records.breeding_id', '=', 'breeding_records.breeding_id')
                             ->whereColumn('breeding_records.dam_id', 'livestock.animal_id');
                    });
    }

    public function offspringRecord(): HasOne
    {
        return $this->hasOne(OffspringRecord::class, 'livestock_id', 'animal_id');
    }

    // =================================================================
    // PRODUCTION TRACKING
    // =================================================================

    public function milkYields(): HasMany
    {
        return $this->hasMany(MilkYieldRecord::class, 'animal_id', 'animal_id');
    }

    public function weightRecords(): HasMany
    {
        return $this->hasMany(WeightRecord::class, 'animal_id', 'animal_id');
    }

    public function feedIntakes(): HasMany
    {
        return $this->hasMany(FeedIntakeRecord::class, 'animal_id', 'animal_id');
    }

    public function productionFactors(): HasMany
    {
        return $this->hasMany(ProductionFactor::class, 'animal_id', 'animal_id');
    }

    // =================================================================
    // FINANCIAL MODULE
    // =================================================================

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'animal_id', 'animal_id');
    }

    public function income(): HasMany
    {
        return $this->hasMany(Income::class, 'animal_id', 'animal_id');
    }

    // =================================================================
    // POWERFUL ACCESSORS
    // =================================================================

    public function getAgeInMonthsAttribute(): int
    {
        return $this->date_of_birth ? $this->date_of_birth->diffInMonths(now()) : 0;
    }

    public function getAgeInYearsAttribute(): float
    {
        return round($this->age_in_months / 12, 1);
    }

    public function getDaysInMilkAttribute(): ?int
    {
        $lastBirth = $this->offspringAsDam()->latest('date_of_birth')->first()?->date_of_birth;
        return $lastBirth ? $lastBirth->diffInDays(now()) : null;
    }

    public function getLatestWeightAttribute(): ?float
    {
        return $this->weightRecords()->latest('record_date')->first()?->weight_kg;
    }

    public function getLatestBcsAttribute(): ?float
    {
        return $this->weightRecords()->latest('record_date')->first()?->body_condition_score;
    }

    public function getTotalMilkLast30DaysAttribute(): float
    {
        return $this->milkYields()
            ->where('yield_date', '>=', now()->subDays(30))
            ->sum('quantity_liters');
    }

    public function getAverageDailyMilkAttribute(): ?float
    {
        $total = $this->total_milk_last_30_days;
        return $total > 0 ? round($total / 30, 2) : null;
    }

    public function getTotalFeedLast30DaysAttribute(): float
    {
        return $this->feedIntakes()
            ->where('intake_date', '>=', now()->subDays(30))
            ->sum('quantity');
    }

    public function getAdgLast60DaysAttribute(): ?float
    {
        $records = $this->weightRecords()
            ->where('record_date', '>=', now()->subDays(60))
            ->orderBy('record_date')
            ->limit(2)
            ->get();

        if ($records->count() < 2) return null;

        $days = $records->first()->record_date->diffInDays($records->last()->record_date);
        return $days > 0 ? round(($records->last()->weight_kg - $records->first()->weight_kg) / $days, 3) : null;
    }

    public function getTotalRevenueAttribute(): float
    {
        return $this->income()->sum('amount');
    }

    public function getTotalExpensesAttribute(): float
    {
        return $this->expenses()->sum('amount') + ($this->purchase_cost ?? 0);
    }

    public function getNetProfitAttribute(): float
    {
        return $this->total_revenue - $this->total_expenses;
    }

    public function getProfitPerDayAttribute(): ?float
    {
        $days = $this->date_of_birth?->diffInDays(now()) ?: 1;
        return round($this->net_profit / $days, 2);
    }

    public function getIsProfitableAttribute(): bool
    {
        return $this->net_profit > 0;
    }

    public function getMarketReadyAttribute(): bool
    {
        $target = $this->species->species_name === 'Cattle' ? 450 : 35;
        return ($this->latest_weight ?? 0) >= $target;
    }

    public function getProjectedSaleDateAttribute(): ?string
    {
        if ($this->market_ready) return 'Ready Now';

        $adg = $this->adg_last_60_days ?? 0.8;
        if ($adg <= 0) return 'Unknown';

        $target = $this->species->species_name === 'Cattle' ? 450 : 35;
        $needed = $target - ($this->latest_weight ?? 0);
        $days = ceil($needed / $adg);

        return now()->addDays($days)->format('Y-m-d');
    }

    public function getLactationNumberAttribute(): int
    {
        return $this->offspringAsDam()->count();
    }

    public function getEfficiencyScoreAttribute(): string
    {
        $factor = $this->productionFactors()->latest('period_end')->first();
        return $factor?->efficiency_grade ?? 'No Data';
    }

    // =================================================================
    // SCOPES
    // =================================================================

    public function scopeFemale($query)
    {
        return $query->where('sex', 'Female');
    }

    public function scopeMale($query)
    {
        return $query->where('sex', 'Male');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'Active');
    }

    public function scopeMilking($query)
    {
        return $query->where('sex', 'Female')
                     ->where('status', 'Active')
                     ->whereHas('milkYields');
    }

    public function scopePregnant($query)
    {
        return $query->whereHas('breedingAsDam', fn($q) => 
            $q->where('status', 'Confirmed Pregnant')
        );
    }

    public function scopeDueSoon($query, $days = 14)
    {
        return $query->whereHas('breedingAsDam', fn($q) => 
            $q->whereBetween('expected_delivery_date', [now(), now()->addDays($days)])
        );
    }

    public function scopeMarketReady($query)
    {
        return $query->whereRaw('(current_weight_kg >= CASE 
            WHEN species_id = 1 THEN 450 
            WHEN species_id = 2 THEN 35 
            ELSE 100 END)');
    }

    public function scopeProfitable($query)
    {
        return $query->whereHas('income', fn($q) => 
            $q->selectRaw('animal_id, SUM(amount) as total_income')
              ->groupBy('animal_id')
              ->havingRaw('total_income > (
                  SELECT COALESCE(SUM(amount), 0) + COALESCE(purchase_cost, 0)
                  FROM expenses e2 
                  LEFT JOIN livestock l2 ON e2.animal_id = l2.animal_id
                  WHERE e2.animal_id = livestock.animal_id
              )')
        );
    }
}