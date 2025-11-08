<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Farmer extends Model
{
    protected $primaryKey = 'id'; // assuming standard 'id' in farmers table
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'farm_name',
        'farm_purpose',
        'location_id',
        'total_land_acres',
        'years_experience',
    ];

    protected $casts = [
        'farm_purpose' => 'string',
        'total_land_acres' => 'decimal:2',
        'years_experience' => 'integer',
    ];

    // =================================================================
    // CORE RELATIONSHIPS
    // =================================================================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    // =================================================================
    // LIVESTOCK & BREEDING
    // =================================================================

    public function livestock(): HasMany
    {
        return $this->hasMany(Livestock::class, 'farmer_id');
    }

    public function breedingRecords(): HasMany
    {
        return $this->hasManyThrough(BreedingRecord::class, Livestock::class, 'farmer_id', 'dam_id', 'id', 'animal_id');
    }

    public function birthRecords(): HasMany
    {
        return $this->hasManyThrough(BirthRecord::class, Livestock::class, 'farmer_id', 'breeding_id', 'id', 'animal_id')
                     ->join('breeding_records', 'birth_records.breeding_id', '=', 'breeding_records.breeding_id');
    }

    public function offspringRecords(): HasMany
    {
        return $this->hasManyThrough(OffspringRecord::class, BirthRecord::class, 'breeding_id', 'birth_id');
    }

    // =================================================================
    // PRODUCTION & EFFICIENCY
    // =================================================================

    public function milkYields(): HasMany
    {
        return $this->hasManyThrough(MilkYieldRecord::class, Livestock::class, 'farmer_id', 'animal_id', 'id', 'animal_id');
    }

    public function weightRecords(): HasMany
    {
        return $this->hasManyThrough(WeightRecord::class, Livestock::class, 'farmer_id', 'animal_id', 'id', 'animal_id');
    }

    public function feedIntakes(): HasMany
    {
        return $this->hasManyThrough(FeedIntakeRecord::class, Livestock::class, 'farmer_id', 'animal_id', 'id', 'animal_id');
    }

    public function productionFactors(): HasMany
    {
        return $this->hasManyThrough(ProductionFactor::class, Livestock::class, 'farmer_id', 'animal_id', 'id', 'animal_id');
    }

    // =================================================================
    // FINANCIAL MODULE
    // =================================================================

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'farmer_id');
    }

    public function income(): HasMany
    {
        return $this->hasMany(Income::class, 'farmer_id');
    }

    // =================================================================
    // HELPFUL ACCESSORS & SCOPES
    // =================================================================

    public function getFullAddressAttribute(): string
    {
        return $this->location?->full_address ?? 'No location set';
    }

    public function getExperienceLevelAttribute(): string
    {
        return match (true) {
            $this->years_experience >= 10 => 'Veteran',
            $this->years_experience >= 5  => 'Experienced',
            $this->years_experience >= 1  => 'Beginner',
            default => 'New Farmer',
        };
    }

    // Total animals
    public function getTotalAnimalsAttribute(): int
    {
        return $this->livestock()->count();
    }

    // Active milking cows
    public function getMilkingCowsCountAttribute(): int
    {
        return $this->livestock()
            ->where('sex', 'Female')
            ->where('status', 'Active')
            ->whereHas('milkYields')
            ->count();
    }

    // Today's milk income
    public function getTodayMilkIncomeAttribute(): float
    {
        return $this->income()
            ->where('income_date', today())
            ->whereHas('category', fn($q) => $q->where('category_name', 'Milk Sale'))
            ->sum('amount');
    }

    // This month's profit
    public function getMonthlyProfitAttribute(): float
    {
        $income = $this->income()->thisMonth()->sum('amount');
        $expenses = $this->expenses()->thisMonth()->sum('amount');
        return $income - $expenses;
    }

    // Top earning animal
    public function getTopEarnerAttribute()
    {
        return $this->livestock()
            ->withSum('income', 'amount')
            ->orderByDesc('income_sum_amount')
            ->first();
    }

    // Most expensive animal to maintain
    public function getCostliestAnimalAttribute()
    {
        return $this->livestock()
            ->withSum('expenses', 'amount')
            ->orderByDesc('expenses_sum_amount')
            ->first();
    }

    // Scopes
    public function scopeLargeScale($query)
    {
        return $query->where('total_land_acres', '>=', 50);
    }

    public function scopeDairyFocused($query)
    {
        return $query->where('farm_purpose', 'like', '%Dairy%');
    }
}