<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class FeedInventory extends Model
{
    // =================================================================
    // TABLE CONFIGURATION
    // =================================================================

    protected $table = 'feed_inventory';
    protected $primaryKey = 'feed_id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = true;

    protected $fillable = [
        'feed_name',
        'feed_type', // Concentrate, Forage, Mineral, Supplement
        'unit',      // kg, bag, liter, bale
        'protein_percentage',
        'energy_content_mj',
        'cost_per_unit',
        'supplier',
        'batch_number',
        'expiry_date',
        'storage_location',
        'is_active',
    ];

    protected $casts = [
        'protein_percentage'   => 'decimal:2',
        'energy_content_mj'    => 'decimal:2',
        'cost_per_unit'        => 'decimal:2',
        'expiry_date'          => 'date',
        'is_active'            => 'boolean',
        'created_at'           => 'datetime',
        'updated_at'           => 'datetime',
    ];

    // =================================================================
    // CORE RELATIONSHIPS
    // =================================================================

    public function stockRecords(): HasMany
    {
        return $this->hasMany(FeedStock::class, 'feed_id', 'feed_id');
    }

    public function intakeRecords(): HasManyThrough
    {
        return $this->hasManyThrough(
            FeedIntakeRecord::class,
            FeedStock::class,
            'feed_id',
            'stock_id',
            'feed_id',
            'stock_id'
        );
    }

    public function expenses(): HasManyThrough
    {
        return $this->hasManyThrough(
            Expense::class,
            FeedStock::class,
            'feed_id',
            'animal_id', // via intake → livestock
            'feed_id',
            null
        );
    }

    public function animalsFed(): HasManyThrough
    {
        return $this->hasManyThrough(
            Livestock::class,
            FeedIntakeRecord::class,
            'feed_id',
            'animal_id',
            'feed_id',
            'animal_id'
        );
    }

    // =================================================================
    // DYNAMIC ACCESSORS (REAL-TIME KPIs)
    // =================================================================

    public function getCurrentStockAttribute(): float
    {
        return $this->stockRecords()->sum('quantity') ?? 0;
    }

    public function getStockValueAttribute(): float
    {
        return $this->stockRecords()
            ->sum(\DB::raw('quantity * cost_per_unit_at_purchase'));
    }

    public function getAverageCostPerKgAttribute(): ?float
    {
        $totalValue = $this->stock_value;
        $totalQty   = $this->current_stock;
        return $totalQty > 0 ? round($totalValue / $totalQty, 2) : null;
    }

    public function getTotalConsumedAttribute(): float
    {
        return $this->intakeRecords()->sum('quantity') ?? 0;
    }

    public function getTotalConsumedCostAttribute(): float
    {
        return $this->intakeRecords()
            ->sum(\DB::raw('quantity * cost_per_unit_used'));
    }

    public function getFeedConversionRatioAttribute(): ?float
    {
        $milk = $this->animalsFed()
            ->withSum('milkYields', 'quantity_liters')
            ->sum('milk_yields_sum_quantity_liters');

        $feed = $this->total_consumed;
        return $feed > 0 ? round($feed / $milk, 3) : null;
    }

    public function getCostPerLiterMilkAttribute(): ?float
    {
        $milk = $this->animalsFed()
            ->withSum('milkYields', 'quantity_liters')
            ->sum('milk_yields_sum_quantity_liters');

        $cost = $this->total_consumed_cost;
        return $milk > 0 ? round($cost / $milk, 2) : null;
    }

    public function getDaysOfStockLeftAttribute(): ?int
    {
        $dailyUse = $this->intakeRecords()
            ->where('intake_date', '>=', now()->subDays(7))
            ->avg('quantity') * 7; // 7-day avg

        return $dailyUse > 0 ? (int) floor($this->current_stock / $dailyUse) : null;
    }

    public function getIsLowStockAttribute(): bool
    {
        return $this->days_of_stock_left !== null && $this->days_of_stock_left <= 7;
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->expiry_date && $this->expiry_date->lt(now());
    }

    public function getIsExpiringSoonAttribute(): bool
    {
        return $this->expiry_date && $this->expiry_date->diffInDays(now()) <= 30;
    }

    public function getProteinPerKesAttribute(): ?float
    {
        $costPerKg = $this->average_cost_per_kg;
        return $costPerKg > 0 ? round($this->protein_percentage / $costPerKg, 3) : null;
    }

    public function getBestValueAttribute(): bool
    {
        $avg = FeedInventory::highProtein()->avg('protein_per_kes');
        return $this->protein_per_kes > $avg * 1.1;
    }

    // =================================================================
    // SCOPES & ALERTS
    // =================================================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeConcentrate($query)
    {
        return $query->where('feed_type', 'Concentrate');
    }

    public function scopeForage($query)
    {
        return $query->where('feed_type', 'Forage');
    }

    public function scopeMineral($query)
    {
        return $query->where('feed_type', 'Mineral');
    }

    public function scopeHighProtein($query, $min = 16.0)
    {
        return $query->where('protein_percentage', '>=', $min);
    }

    public function scopeHighEnergy($query, $min = 11.0)
    {
        return $query->where('energy_content_mj', '>=', $min);
    }

    public function scopeInStock($query)
    {
        return $query->whereHas('stockRecords', fn($q) => 
            $q->havingRaw('SUM(quantity) > 0')
        );
    }

    public function scopeLowStock($query, $days = 7)
    {
        return $query->whereHas('stockRecords', function ($q) use ($days) {
            $q->selectRaw('feed_id, 
                SUM(quantity) as stock,
                AVG(CASE WHEN intake_date >= ? THEN quantity ELSE 0 END) * 7 as daily_use', 
                [now()->subDays(7)]
            )
            ->havingRaw('stock / NULLIF(daily_use, 0) <= ?', [$days]);
        });
    }

    public function scopeExpiringSoon($query, $days = 30)
    {
        return $query->where('expiry_date', '<=', now()->addDays($days))
                     ->where('expiry_date', '>', now());
    }

    public function scopeExpired($query)
    {
        return $query->where('expiry_date', '<', now());
    }

    public function scopeBestValue($query)
    {
        return $query->whereRaw('protein_percentage / cost_per_unit >= (
            SELECT AVG(protein_percentage / cost_per_unit) * 1.1 
            FROM feed_inventory 
            WHERE protein_percentage > 0
        )');
    }

    public function scopeUsedThisMonth($query)
    {
        return $query->whereHas('intakeRecords', fn($q) => 
            $q->whereMonth('intake_date', now()->month)
        );
    }

    public function scopeBySupplier($query, $supplier)
    {
        return $query->where('supplier', 'like', "%{$supplier}%");
    }
}