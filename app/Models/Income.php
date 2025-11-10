<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Carbon\Carbon;

class Income extends Model
{
    // =================================================================
    // TABLE CONFIGURATION
    // =================================================================

    protected $table = 'incomes';
    protected $primaryKey = 'income_id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = true;

    protected $fillable = [
        'farmer_id',
        'category_id',
        'animal_id',
        'amount',
        'quantity',                    // liters, kg, heads
        'unit_price',
        'income_date',
        'payment_method',              // Cash, M-Pesa, Bank, Cheque, Mobile Money
        'buyer_customer',
        'phone_number',
        'receipt_number',
        'source_reference',            // M-Pesa code, bank ref
        'mpesa_transaction_code',
        'description',
        'recorded_by',
        'verified_by',
        'attachment_path',
        'status',                      // Pending, Verified, Rejected
        'is_bonus',
        'bonus_reason',
    ];

    protected $casts = [
        'income_date'         => 'date',
        'amount'              => 'decimal:2',
        'quantity'            => 'decimal:2',
        'unit_price'          => 'decimal:2',
        'is_bonus'            => 'boolean',
        'created_at'          => 'datetime',
        'updated_at'          => 'datetime',
    ];

    // =================================================================
    // CORE RELATIONSHIPS
    // =================================================================

    public function farmer(): BelongsTo
    {
        return $this->belongsTo(Farmer::class, 'farmer_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(IncomeCategory::class, 'category_id', 'id');
    }

    public function animal(): BelongsTo
    {
        return $this->belongsTo(Livestock::class, 'animal_id', 'animal_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
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
        )->whereDate('milk_yield_records.yield_date', $this->income_date);
    }

    // Polymorphic: receipts, photos, PDFs
    public function attachments(): MorphMany
    {
        return $this->morphMany(Media::class, 'model');
    }

    // =================================================================
    // DYNAMIC PROFIT KPIs
    // =================================================================

    public function getIsAnimalSpecificAttribute(): bool
    {
        return !is_null($this->animal_id);
    }

    public function getFormattedAmountAttribute(): string
    {
        return 'KES ' . number_format($this->amount, 2);
    }

    public function getIs030Attribute(): bool
    {
        return str($this->payment_method)->contains(['M-Pesa', 'Mobile Money']);
    }

    public function getIsCashAttribute(): bool
    {
        return $this->payment_method === 'Cash';
    }

    public function getIsMilkIncomeAttribute(): bool
    {
        return $this->category?->category_name === 'Milk Sale' ||
               str($this->category?->category_name)->contains('Milk');
    }

    public function getIsAnimalSaleAttribute(): bool
    {
        return $this->category?->category_name === 'Animal Sale';
    }

    public function getProfitAttribute(): ?float
    {
        if (!$this->animal) return null;
        $totalCost = $this->animal->total_expenses + ($this->animal->purchase_cost ?? 0);
        return round($this->amount - $totalCost, 2);
    }

    public function getRoiPercentageAttribute(): ?float
    {
        if (!$this->animal || $this->profit <= 0) return null;
        $totalCost = $this->animal->total_expenses + ($this->animal->purchase_cost ?? 0);
        return $totalCost > 0 ? round(($this->profit / $totalCost) * 100, 1) : null;
    }

    public function getPricePerLiterAttribute(): ?float
    {
        return $this->quantity > 0 ? round($this->amount / $this->quantity, 2) : null;
    }

    public function getBonusAmountAttribute(): ?float
    {
        return $this->is_bonus ? $this->amount : 0;
    }

    public function getIsHighValueAttribute(): bool
    {
        return $this->amount >= 50000;
    }

    public function getIsVerifiedAttribute(): bool
    {
        return $this->status === 'Verified';
    }

    public function getIsPendingAttribute(): bool
    {
        return in_array($this->status, ['Pending', 'Posted']);
    }

    // =================================================================
    // SCOPES & ALERTS
    // =================================================================

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('income_date', now()->month)
                     ->whereYear('income_date', now()->year);
    }

    public function scopeThisYear($query)
    {
        return $query->whereYear('income_date', now()->year);
    }

    public function scopeLast30Days($query)
    {
        return $query->where('income_date', '>=', now()->subDays(30));
    }

    public function scopeMilkSales($query)
    {
        return $query->whereHas('category', fn($q) =>
            $q->where('category_name', 'like', '%Milk%')
        );
    }

    public function scopeAnimalSales($query)
    {
        return $query->whereHas('category', fn($q) =>
            $q->where('category_name', 'like', '%Animal%')
              ->orWhere('category_name', 'like', '%Sale%')
        );
    }

    public function scopeMobileMoney($query)
    {
        return $query->whereIn('payment_method', ['M-Pesa', 'Mobile Money', 'Airtel Money']);
    }

    public function scopeCash($query)
    {
        return $query->where('payment_method', 'Cash');
    }

    public function scopeForAnimal($query, $animalId)
    {
        return $query->where('animal_id', $animalId);
    }

    public function scopeUnverified($query)
    {
        return $query->whereIn('status', ['Pending', 'Posted']);
    }

    public function scopeHighValue($query, $amount = 100000)
    {
        return $query->where('amount', '>=', $amount);
    }

    public function scopeBonus($query)
    {
        return $query->where('is_bonus', true);
    }

    public function scopeByCustomer($query, $name)
    {
        return $query->where('buyer_customer', 'like', "%{$name}%");
    }

    public function scopeMpesaCode($query, $code)
    {
        return $query->where('mpesa_transaction_code', $code);
    }

    // =================================================================
    // AUTO-VERIFICATION & BONUS LOGIC
    // =================================================================

    protected static function booted()
    {
        static::creating(function ($income) {
            if (!$income->status) {
                $income->status = 'Pending';
            }
            if (!$income->recorded_by) {
                $income->recorded_by = auth()->id();
            }
            if ($income->isMilkIncome && $income->quantity && !$income->unit_price) {
                $income->unit_price = $income->amount / $income->quantity;
            }
        });

        static::created(function ($income) {
            // Auto-verify M-Pesa transactions
            if ($income->mpesa_transaction_code && $income->payment_method === 'M-Pesa') {
                $income->update(['status' => 'Verified', 'verified_by' => auth()->id()]);
            }

            // Auto-bonus for top performers
            if ($income->isMilkIncome && $income->animal) {
                $factor = $income->animal->latestProductionFactor;
                if ($factor?->is_top_performer) {
                    // Create bonus income
                    Income::create([
                        'farmer_id' => $income->farmer_id,
                        'category_id' => IncomeCategory::where('category_name', 'Milk Bonus')->first()?->category_id,
                        'animal_id' => $income->animal_id,
                        'amount' => 5000,
                        'income_date' => $income->income_date,
                        'description' => 'Monthly Bonus - Top Performer',
                        'is_bonus' => true,
                        'bonus_reason' => 'FCR ≤ 1.3 & Profit ≥ KES 300/day',
                        'status' => 'Verified',
                    ]);
                }
            }
        });
    }

    // =================================================================
    // DASHBOARD-READY QUERIES
    // =================================================================

    public static function dailyTrend()
    {
        return static::selectRaw('income_date, SUM(amount) as total')
            ->where('income_date', '>=', now()->subDays(30))
            ->groupBy('income_date')
            ->orderBy('income_date')
            ->pluck('total', 'income_date');
    }

    public static function topCustomers($limit = 5)
    {
        return static::selectRaw('buyer_customer, SUM(amount) as total')
            ->whereNotNull('buyer_customer')
            ->groupBy('buyer_customer')
            ->orderByDesc('total')
            ->limit($limit)
            ->pluck('total', 'buyer_customer');
    }
}
