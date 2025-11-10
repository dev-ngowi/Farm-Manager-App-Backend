<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Breed extends Model
{
    // =================================================================
    // TABLE CONFIGURATION
    // =================================================================

    protected $table = 'breeds';
    protected $primaryKey = 'id'; // You're using standard `id` (not `breed_id`)
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = true; // created_at exists

    protected $fillable = [
        'species_id',
        'breed_name',
        'origin',
        'purpose',
        'average_weight_kg',
        'maturity_months',
    ];

    protected $casts = [
        'purpose' => 'string',
        'average_weight_kg' => 'decimal:2',
        'maturity_months' => 'integer',
        'created_at' => 'datetime',
    ];

    // =================================================================
    // CORE RELATIONSHIPS
    // =================================================================

    /**
     * Breed belongs to a Species (e.g., Friesian → Cattle)
     */
    public function species(): BelongsTo
    {
        return $this->belongsTo(Species::class, 'species_id', 'id');
    }

    /**
     * All animals of this breed
     */
    public function livestock(): HasMany
    {
        return $this->hasMany(Livestock::class, 'breed_id', 'id');
    }

    // =================================================================
    // PRODUCTION & PERFORMANCE
    // =================================================================

    public function milkYields(): HasManyThrough
    {
        return $this->hasManyThrough(
            MilkYieldRecord::class,
            Livestock::class,
            'breed_id',
            'animal_id',
            'id',
            'animal_id'
        );
    }

    public function weightRecords(): HasManyThrough
    {
        return $this->hasManyThrough(
            WeightRecord::class,
            Livestock::class,
            'breed_id',
            'animal_id',
            'id',
            'animal_id'
        );
    }

    public function feedIntakes(): HasManyThrough
    {
        return $this->hasManyThrough(
            FeedIntakeRecord::class,
            Livestock::class,
            'breed_id',
            'animal_id',
            'id',
            'animal_id'
        );
    }

    public function breedingRecordsAsDam(): HasManyThrough
    {
        return $this->hasManyThrough(
            BreedingRecord::class,
            Livestock::class,
            'breed_id',
            'dam_id',
            'id',
            'animal_id'
        );
    }

    public function breedingRecordsAsSire(): HasManyThrough
    {
        return $this->hasManyThrough(
            BreedingRecord::class,
            Livestock::class,
            'breed_id',
            'sire_id',
            'id',
            'animal_id'
        );
    }

    public function birthRecords(): HasManyThrough
    {
        return $this->hasManyThrough(
            BirthRecord::class,
            BreedingRecord::class,
            'dam_id', // via BreedingRecord
            'breeding_id',
            null,
            'breeding_id'
        )->whereHas('breeding', fn($q) => $q->whereHas('dam', fn($d) => $d->where('breed_id', $this->id)));
    }

    public function productionFactors(): HasManyThrough
    {
        return $this->hasManyThrough(
            ProductionFactor::class,
            Livestock::class,
            'breed_id',
            'animal_id',
            'id',
            'animal_id'
        );
    }

    // =================================================================
    // FINANCIAL INSIGHTS
    // =================================================================

    public function income(): HasManyThrough
    {
        return $this->hasManyThrough(
            Income::class,
            Livestock::class,
            'breed_id',
            'animal_id',
            'id',
            'animal_id'
        );
    }

    public function expenses(): HasManyThrough
    {
        return $this->hasManyThrough(
            Expense::class,
            Livestock::class,
            'breed_id',
            'animal_id',
            'id',
            'animal_id'
        );
    }

    // =================================================================
    // POWERFUL ACCESSORS
    // =================================================================

    public function getTotalAnimalsAttribute(): int
    {
        return $this->livestock()->count();
    }

    public function getActiveFemalesAttribute(): int
    {
        return $this->livestock()
            ->where('sex', 'Female')
            ->where('status', 'Active')
            ->count();
    }

    public function getAverageMilkPerCowAttribute(): ?float
    {
        return $this->milkYields()
            ->where('yield_date', '>=', now()->subDays(30))
            ->selectRaw('animal_id, AVG(quantity_liters) as avg_milk')
            ->groupBy('animal_id')
            ->avg('avg_milk');
    }

    public function getTotalMilkLast30DaysAttribute(): float
    {
        return $this->milkYields()
            ->where('yield_date', '>=', now()->subDays(30))
            ->sum('quantity_liters');
    }

    public function getAverageAdgAttribute(): ?float
    {
        return $this->weightRecords()
            ->selectRaw('animal_id,
                (MAX(weight_kg) - MIN(weight_kg)) /
                DATEDIFF(MAX(record_date), MIN(record_date)) as adg')
            ->groupBy('animal_id')
            ->avg('adg');
    }

    public function getTotalRevenueAttribute(): float
    {
        return $this->income()->sum('amount');
    }

    public function getTotalCostAttribute(): float
    {
        return $this->expenses()->sum('amount') +
               ($this->livestock()->sum('purchase_cost') ?? 0);
    }

    public function getNetProfitAttribute(): float
    {
        return $this->total_revenue - $this->total_cost;
    }

    public function getProfitPerCowAttribute(): ?float
    {
        $count = $this->total_animals;
        return $count > 0 ? round($this->net_profit / $count, 2) : null;
    }

    public function getEfficiencyGradeAttribute(): string
    {
        $milkPerFeed = $this->productionFactors()
            ->where('period_start', '>=', now()->subMonth())
            ->avg('milk_per_kg_feed');

        return match (true) {
            $milkPerFeed >= 1.0 => 'Excellent',
            $milkPerFeed >= 0.8 => 'Good',
            $milkPerFeed >= 0.6 => 'Average',
            $milkPerFeed < 0.6  => 'Poor',
            default => 'No Data',
        };
    }

    // =================================================================
    // SCOPES
    // =================================================================

    public function scopeDairy($query)
    {
        return $query->where('purpose', 'like', '%Milk%')
                     ->orWhere('purpose', 'Dual-purpose');
    }

    public function scopeBeef($query)
    {
        return $query->where('purpose', 'like', '%Meat%');
    }

    public function scopeHighYielding($query)
    {
        return $query->whereHas('livestock', function ($q) {
            $q->whereHas('milkYields', function ($m) {
                $m->selectRaw('animal_id, AVG(quantity_liters) as avg_yield')
                  ->groupBy('animal_id')
                  ->having('avg_yield', '>', 20);
            });
        });
    }

    public function scopeProfitable($query)
    {
        return $query->whereHas('livestock', function ($q) {
            $q->withSum('income', 'amount')
              ->withSum('expenses', 'amount')
              ->havingRaw('income_sum_amount > expenses_sum_amount');
        });
    }

    public function scopeTanzanianFavorites($query)
    {
        return $query->whereIn('breed_name', [
            'Friesian', 'Ayrshire', 'Jersey', 'Sahiwal',
            'Boran', 'Zebu', 'Small East African Goat',
            'Galla Goat', 'Boer', 'Red Maasai Sheep'
        ]);
    }
}
