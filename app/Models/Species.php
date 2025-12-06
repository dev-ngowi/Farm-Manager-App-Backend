<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
class Species extends Model
{
    // =================================================================
    // TABLE CONFIGURATION (Matches your migration exactly)
    // =================================================================

    protected $table = 'species';
    protected $primaryKey = 'species_id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = true; // created_at & updated_at exist

    protected $fillable = [
        'species_name',
        'description',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // =================================================================
    // RELATIONSHIPS
    // =================================================================

    /**
     * All breeds of this species (e.g., Friesian, Boran for Cattle)
     */
    public function breeds(): HasMany
    {
        return $this->hasMany(Breed::class, 'species_id', 'id');
    }

    /**
     * All animals of this species on the farm
     */
    public function livestock(): HasMany
    {
        return $this->hasMany(Livestock::class, 'species_id', 'id');
    }

    public function topBreed(): HasOne
{
    return $this->hasOne(Breed::class, 'species_id', 'id')
        ->select(
            'breeds.id',
            'breeds.breed_name',
            'breeds.purpose',
            'breeds.origin',
            'breeds.species_id',
            DB::raw('(SELECT COUNT(*) FROM livestock WHERE livestock.breed_id = breeds.id) as livestock_count')
        )
        ->orderByDesc('livestock_count');
}

    /**
     * All milk records from animals of this species
     */
    public function milkYields(): HasManyThrough
    {
        return $this->hasManyThrough(
            MilkYieldRecord::class,
            Livestock::class,
            'species_id',     // Foreign key on livestock table
            'animal_id',      // Foreign key on milk_yield_records
            'species_id',     // Local key on species
            'animal_id'       // Local key on livestock
        );
    }

    /**
     * All weight records for this species
     */
    public function weightRecords(): HasManyThrough
    {
        return $this->hasManyThrough(
            WeightRecord::class,
            Livestock::class,
            'species_id',
            'animal_id',
            'species_id',
            'animal_id'
        );
    }

    /**
     * All feed intake records for this species
     */
    public function feedIntakes(): HasManyThrough
    {
        return $this->hasManyThrough(
            FeedIntakeRecord::class,
            Livestock::class,
            'species_id',
            'animal_id',
            'species_id',
            'animal_id'
        );
    }

    /**
     * All breeding records (as dam or sire) for this species
     */
    public function breedingRecordsAsDam(): HasManyThrough
    {
        return $this->hasManyThrough(
            BreedingRecord::class,
            Livestock::class,
            'species_id',
            'dam_id',
            'species_id',
            'animal_id'
        );
    }

    public function breedingRecordsAsSire(): HasManyThrough
    {
        return $this->hasManyThrough(
            BreedingRecord::class,
            Livestock::class,
            'species_id',
            'sire_id',
            'species_id',
            'animal_id'
        );
    }

    /**
     * All production efficiency metrics for this species
     */
    public function productionFactors(): HasManyThrough
    {
        return $this->hasManyThrough(
            ProductionFactor::class,
            Livestock::class,
            'species_id',
            'animal_id',
            'species_id',
            'animal_id'
        );
    }

    // =================================================================
    // ACCESSORS & SCOPES
    // =================================================================

    public function getTotalAnimalsAttribute(): int
    {
        return $this->livestock()->count();
    }

    public function getActiveAnimalsAttribute(): int
    {
        return $this->livestock()->where('status', 'Active')->count();
    }

    public function getTotalMilkTodayAttribute(): float
    {
        return $this->milkYields()
            ->where('yield_date', today())
            ->sum('quantity_liters');
    }

    public function getAverageDailyMilkAttribute(): float
    {
        return $this->milkYields()
            ->where('yield_date', '>=', now()->subDays(30))
            ->avg('quantity_liters') ?? 0;
    }

    public function getTopBreedAttribute()
    {
        return $this->breeds()
            ->withCount('livestock')
            ->orderByDesc('livestock_count')
            ->first();
    }

    public function getAverageWeightAttribute(): ?float
    {
        return $this->weightRecords()
            ->latest('record_date')
            ->avg('weight_kg');
    }

    // Scopes
    public function scopeDairy($query)
    {
        return $query->whereIn('species_name', ['Cattle', 'Goat', 'Sheep']);
    }

    public function scopeBeef($query)
    {
        return $query->where('species_name', 'Cattle');
    }

    public function scopeWithActiveAnimals($query)
    {
        return $query->whereHas('livestock', fn($q) => $q->where('status', 'Active'));
    }

    public function scopePopularInTanzania($query)
    {
        return $query->whereIn('species_name', [
            'Cattle', 'Goat', 'Sheep', 'Chicken', 'Pig'
        ]);
    }
}
