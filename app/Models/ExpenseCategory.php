<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Carbon\Carbon;

class ExpenseCategory extends Model
{
    // =================================================================
    // TABLE CONFIGURATION
    // =================================================================

    protected $table = 'expense_categories';
    protected $primaryKey = 'category_id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = true;

    protected $fillable = [
        'category_name',
        'parent_category_id',    // For sub-categories: e.g., "Vaccines" → "Veterinary"
        'description',
        'is_animal_specific',
        'is_recurring',
        'budget_monthly',
        'budget_yearly',
        'tax_deductible',
        'color_code',            // For charts: #FF6B6B
        'icon',                  // Heroicon: truck, syringe, users
        'sort_order',
    ];

    protected $casts = [
        'is_animal_specific' => 'boolean',
        'is_recurring'       => 'boolean',
        'tax_deductible'     => 'boolean',
        'budget_monthly'     => 'decimal:2',
        'budget_yearly'      => 'decimal:2',
        'created_at'         => 'datetime',
        'updated_at'         => 'datetime',
    ];

    // =================================================================
    // CORE RELATIONSHIPS
    // =================================================================

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'category_id', 'category_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'parent_category_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(ExpenseCategory::class, 'parent_category_id');
    }

    public function animals(): HasManyThrough
    {
        return $this->hasManyThrough(
            Livestock::class,
            Expense::class,
            'category_id',
            'animal_id',
            'category_id',
            'animal_id'
        );
    }

    // =================================================================
    // DYNAMIC FINANCIAL KPIs
    // =================================================================

    public function getTotalSpentAttribute(): float
    {
        return $this->expenses()->sum('amount') ?? 0;
    }

    public function getSpentThisMonthAttribute(): float
    {
        return $this->expenses()
            ->whereMonth('expense_date', now()->month)
            ->whereYear('expense_date', now()->year)
            ->sum('amount') ?? 0;
    }

    public function getSpentThisYearAttribute(): float
    {
        return $this->expenses()
            ->whereYear('expense_date', now()->year)
            ->sum('amount') ?? 0;
    }

    public function getBudgetRemainingAttribute(): ?float
    {
        return $this->budget_monthly 
            ? round($this->budget_monthly - $this->spent_this_month, 2)
            : null;
    }

    public function getIsOverBudgetAttribute(): bool
    {
        return $this->budget_monthly && $this->spent_this_month > $this->budget_monthly;
    }

    public function getPercentageOfTotalAttribute(): ?float
    {
        $total = Expense::sum('amount');
        return $total > 0 ? round(($this->total_spent / $total) * 100, 1) : 0;
    }

    public function getCostPerLiterAttribute(): ?float
    {
        $milk = MilkYieldRecord::whereYear('yield_date', now()->year)
            ->sum('quantity_liters');
        return $milk > 0 ? round($this->spent_this_year / $milk, 3) : null;
    }

    public function getAnimalsAffectedAttribute(): int
    {
        return $this->animals()->distinct('animal_id')->count('animal_id');
    }

    public function getAvgCostPerAnimalAttribute(): ?float
    {
        $count = $this->animals_affected;
        return $count > 0 ? round($this->total_spent / $count, 2) : null;
    }

    public function getIsHighImpactAttribute(): bool
    {
        return $this->percentage_of_total >= 15;
    }

    public function getTrendVsLastMonthAttribute(): ?float
    {
        $lastMonth = $this->expenses()
            ->whereMonth('expense_date', now()->subMonth()->month)
            ->whereYear('expense_date', now()->subMonth()->year)
            ->sum('amount');

        return $lastMonth > 0 
            ? round((($this->spent_this_month - $lastMonth) / $lastMonth) * 100, 1)
            : null;
    }

    public function getIsRisingFastAttribute(): bool
    {
        return $this->trend_vs_last_month && $this->trend_vs_last_month > 30;
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

    public function scopeRecurring($query)
    {
        return $query->where('is_recurring', true);
    }

    public function scopeOverBudget($query)
    {
        return $query->whereRaw('budget_monthly < (
            SELECT SUM(amount) FROM expenses e 
            WHERE e.category_id = expense_categories.category_id 
              AND MONTH(e.expense_date) = MONTH(CURDATE())
              AND YEAR(e.expense_date) = YEAR(CURDATE())
        )');
    }

    public function scopeHighImpact($query)
    {
        return $query->whereRaw('(
            SELECT SUM(amount) FROM expenses e 
            WHERE e.category_id = expense_categories.category_id
        ) >= 0.15 * (SELECT SUM(amount) FROM expenses)');
    }

    public function scopeRisingFast($query)
    {
        return $query->whereRaw('
            (SELECT SUM(amount) FROM expenses e 
             WHERE e.category_id = expense_categories.category_id 
               AND MONTH(e.expense_date) = MONTH(CURDATE())
               AND YEAR(e.expense_date) = YEAR(CURDATE())
            ) > 1.3 * (
                SELECT SUM(amount) FROM expenses e2 
                WHERE e2.category_id = expense_categories.category_id 
                  AND MONTH(e2.expense_date) = MONTH(CURDATE() - INTERVAL 1 MONTH)
            )
        ');
    }

    public function scopeCommon($query)
    {
        return $query->whereIn('category_name', [
            'Feed', 'Veterinary', 'Labor', 'AI Services', 'Medicine', 
            'Fuel', 'Electricity', 'Water', 'Transport', 'Insurance'
        ]);
    }

    public function scopeFeedRelated($query)
    {
        return $query->where('category_name', 'like', '%Feed%')
                     ->orWhere('category_name', 'like', '%Ration%');
    }

    public function scopeHealthRelated($query)
    {
        return $query->whereIn('category_name', ['Veterinary', 'Medicine', 'Vaccines', 'Deworming']);
    }

    // =================================================================
    // SEEDER-READY DEFAULT CATEGORIES
    // =================================================================

    public static function defaultCategories(): array
    {
        return [
            ['category_name' => 'Feed',           'is_animal_specific' => true,  'budget_monthly' => 250000, 'color_code' => '#10B981', 'icon' => 'cube'],
            ['category_name' => 'Veterinary',     'is_animal_specific' => true,  'budget_monthly' => 80000,  'color_code' => '#EF4444', 'icon' => 'heart'],
            ['category_name' => 'Labor',          'is_animal_specific' => false, 'budget_monthly' => 120000, 'color_code' => '#3B82F6', 'icon' => 'users'],
            ['category_name' => 'AI Services',    'is_animal_specific' => true,  'budget_monthly' => 40000,  'color_code' => '#8B5CF6', 'icon' => 'beaker'],
            ['category_name' => 'Medicine',       'is_animal_specific' => true,  'budget_monthly' => 30000,  'color_code' => '#F59E0B', 'icon' => 'pill'],
            ['category_name' => 'Fuel',           'is_animal_specific' => false, 'budget_monthly' => 50000,  'color_code' => '#6B7280', 'icon' => 'truck'],
            ['category_name' => 'Electricity',    'is_animal_specific' => false, 'budget_monthly' => 35000,  'color_code' => '#14B8A6', 'icon' => 'bolt'],
        ];
    }
}