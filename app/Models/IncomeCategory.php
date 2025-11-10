<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class IncomeCategory extends Model
{
    // =================================================================
    // TABLE CONFIGURATION
    // =================================================================

    protected $table = 'income_categories';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = true;

    protected $fillable = [
        'category_name',
        'parent_category_id',     // e.g., "Milk Bonus" → "Milk Sales"
        'description',
        'is_animal_specific',
        'is_taxable',
        'default_price_per_unit', // e.g., Milk = 45 TZS/liter
        'unit_of_measure',        // liter, kg, head
        'color_code',             // For charts
        'icon',                   // Heroicons
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_animal_specific' => 'boolean',
        'is_taxable'         => 'boolean',
        'default_price_per_unit' => 'decimal:2',
        'is_active'          => 'boolean',
        'created_at'         => 'datetime',
        'updated_at'         => 'datetime',
    ];

    // =================================================================
    // CORE RELATIONSHIPS
    // =================================================================

    public function incomes(): HasMany
    {
        return $this->hasMany(Income::class, 'category_id', 'id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(IncomeCategory::class, 'parent_category_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(IncomeCategory::class, 'parent_category_id');
    }

    public function animals(): HasManyThrough
    {
        return $this->hasManyThrough(
            Livestock::class,
            Income::class,
            'category_id',
            'animal_id',
            'category_id',
            'animal_id'
        );
    }

    // =================================================================
    // DYNAMIC REVENUE KPIs
    // =================================================================

    public function getTotalEarnedAttribute(): float
    {
        return $this->incomes()->sum('amount') ?? 0;
    }

    public function getEarnedThisMonthAttribute(): float
    {
        return $this->incomes()
            ->whereMonth('income_date', now()->month)
            ->whereYear('income_date', now()->year)
            ->sum('amount') ?? 0;
    }

    public function getEarnedThisYearAttribute(): float
    {
        return $this->incomes()
            ->whereYear('income_date', now()->year)
            ->sum('amount') ?? 0;
    }

    public function getAveragePerDayAttribute(): ?float
    {
        $days = now()->diffInDays($this->incomes()->oldest('income_date')->first()?->income_date ?? now());
        return $days > 0 ? round($this->total_earned / $days, 2) : null;
    }

    public function getPercentageOfTotalAttribute(): ?float
    {
        $total = Income::sum('amount');
        return $total > 0 ? round(($this->total_earned / $total) * 100, 1) : 0;
    }

    public function getUnitsSoldAttribute(): ?float
    {
        return $this->incomes()->sum('quantity') ?? 0;
    }

    public function getAveragePricePerUnitAttribute(): ?float
    {
        $totalAmount = $this->total_earned;
        $totalUnits = $this->units_sold;
        return $totalUnits > 0 ? round($totalAmount / $totalUnits, 2) : null;
    }

    public function getIsMainRevenueAttribute(): bool
    {
        return $this->percentage_of_total >= 60;
    }

    public function getGrowthVsLastMonthAttribute(): ?float
    {
        $lastMonth = $this->incomes()
            ->whereMonth('income_date', now()->subMonth()->month)
            ->whereYear('income_date', now()->subMonth()->year)
            ->sum('amount');

        return $lastMonth > 0
            ? round((($this->earned_this_month - $lastMonth) / $lastMonth) * 100, 1)
            : null;
    }

    public function getIsGrowingAttribute(): bool
    {
        return $this->growth_vs_last_month && $this->growth_vs_last_month > 10;
    }

    public function getProfitMarginAttribute(): ?float
    {
        $costs = Expense::whereHas('category', fn($q) =>
            $q->where('category_name', 'like', '%' . $this->category_name . '%')
        )->sum('amount');
        return $costs > 0 ? round((($this->total_earned - $costs) / $this->total_earned) * 100, 1) : null;
    }

    // =================================================================
    // SCOPES & ALERTS
    // =================================================================

    public function scopeAnimalSpecific($query)
    {
        return $query->where('is_animal_specific', true);
    }

    public function scopeGeneral($query)
    {
        return $query->where('is_animal_specific', false);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeMilkRelated($query)
    {
        return $query->where('category_name', 'like', '%Milk%')
                     ->orWhere('category_name', 'like', '%Dairy%');
    }

    public function scopeMeatRelated($query)
    {
        return $query->where('category_name', 'like', '%Sale%')
                     ->orWhere('category_name', 'like', '%Slaughter%');
    }

    public function scopeMainRevenue($query)
    {
        return $query->whereRaw('(
            SELECT SUM(amount) FROM incomes i
            WHERE i.category_id = income_categories.category_id
        ) >= 0.6 * (SELECT SUM(amount) FROM incomes)');
    }

    public function scopeHighGrowth($query)
    {
        return $query->whereRaw('
            (SELECT SUM(amount) FROM incomes i
             WHERE i.category_id = income_categories.id
               AND MONTH(i.income_date) = MONTH(CURDATE())
               AND YEAR(i.income_date) = YEAR(CURDATE())
            ) > 1.1 * (
                SELECT SUM(amount) FROM incomes i2
                WHERE i2.category_id = income_categories.id
                  AND MONTH(i2.income_date) = MONTH(CURDATE() - INTERVAL 1 MONTH)
            )
        ');
    }

    public function scopeTaxable($query)
    {
        return $query->where('is_taxable', true);
    }

    // =================================================================
    // DEFAULT CATEGORIES (SEEDER READY)
    // =================================================================

    public static function defaultCategories(): array
    {
        return [
            [
                'category_name' => 'Milk Sales',
                'is_animal_specific' => true,
                'default_price_per_unit' => 45.00,
                'unit_of_measure' => 'liter',
                'is_taxable' => true,
                'color_code' => '#3B82F6',
                'icon' => 'droplet',
            ],
            [
                'category_name' => 'Animal Sales',
                'is_animal_specific' => true,
                'unit_of_measure' => 'head',
                'is_taxable' => true,
                'color_code' => '#10B981',
                'icon' => 'cow',
            ],
            [
                'category_name' => 'Manure Sales',
                'is_animal_specific' => false,
                'unit_of_measure' => 'bag',
                'is_taxable' => true,
                'color_code' => '#8B4513',
                'icon' => 'trash',
            ],
            [
                'category_name' => 'Milk Bonus',
                'parent_category_id' => null, // will be set after Milk Sales
                'is_animal_specific' => true,
                'is_taxable' => false,
                'color_code' => '#F59E0B',
                'icon' => 'gift',
            ],
            [
                'category_name' => 'Grants & Subsidies',
                'is_animal_specific' => false,
                'is_taxable' => false,
                'color_code' => '#6366F1',
                'icon' => 'banknote',
            ],
        ];
    }
}
