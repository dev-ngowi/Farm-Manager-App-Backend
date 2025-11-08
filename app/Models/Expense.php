<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Carbon\Carbon;

class Expense extends Model
{
    // =================================================================
    // TABLE CONFIGURATION
    // =================================================================

    protected $table = 'expenses';
    protected $primaryKey = 'expense_id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = true;

    protected $fillable = [
        'farmer_id',
        'category_id',
        'animal_id',
        'amount',
        'expense_date',
        'payment_method',      // Cash, M-Pesa, Bank, Cheque, Credit
        'vendor_supplier',
        'receipt_number',
        'invoice_number',
        'tax_rate',
        'tax_amount',
        'description',
        'recorded_by',
        'approved_by',
        'is_recurring',
        'recurring_frequency', // monthly, quarterly
        'projected_next_date',
        'attachment_path',
        'status',              // Draft, Posted, Approved, Rejected
    ];

    protected $casts = [
        'expense_date'        => 'date',
        'projected_next_date' => 'date',
        'amount'              => 'decimal:2',
        'tax_rate'            => 'decimal:2',
        'tax_amount'          => 'decimal:2',
        'is_recurring'        => 'boolean',
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
        return $this->belongsTo(ExpenseCategory::class, 'category_id', 'category_id');
    }

    public function animal(): BelongsTo
    {
        return $this->belongsTo(Livestock::class, 'animal_id', 'animal_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // Polymorphic: Attach receipts, photos, PDFs
    public function attachments(): MorphMany
    {
        return $this->morphMany(Media::class, 'model');
    }

    // =================================================================
    // DYNAMIC FINANCIAL KPIs
    // =================================================================

    public function getIsAnimalSpecificAttribute(): bool
    {
        return !is_null($this->animal_id);
    }

    public function getFormattedAmountAttribute(): string
    {
        return 'TZS ' . number_format($this->amount, 2);
    }

    public function getTotalWithTaxAttribute(): float
    {
        return round($this->amount + ($this->tax_amount ?? 0), 2);
    }

    public function getIsCashAttribute(): bool
    {
        return $this->payment_method === 'Cash';
    }

    public function getIsMpesaAttribute(): bool
    {
        return $this->payment_method === 'M-Pesa';
    }

    public function getIsApprovedAttribute(): bool
    {
        return $this->status === 'Approved';
    }

    public function getIsPendingAttribute(): bool
    {
        return in_array($this->status, ['Draft', 'Posted']);
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->is_recurring 
            && $this->projected_next_date 
            && $this->projected_next_date->lt(now())
            && !$this->expenses()
                ->where('expense_date', '>=', $this->projected_next_date)
                ->exists();
    }

    public function getCostPerDayAttribute(): ?float
    {
        $start = $this->farmer->created_at ?? now()->subYear();
        $days = $start->diffInDays(now());
        return $days > 0 ? round($this->amount / $days, 2) : null;
    }

    public function getVendorPerformanceAttribute(): ?string
    {
        $total = Expense::where('vendor_supplier', $this->vendor_supplier)
            ->where('farmer_id', $this->farmer_id)
            ->sum('amount');

        $count = Expense::where('vendor_supplier', $this->vendor_supplier)
            ->where('farmer_id', $this->farmer_id)
            ->count();

        return $count > 10 && $total > 500000 ? 'Trusted' : 'New';
    }

    // =================================================================
    // SCOPES & ALERTS
    // =================================================================

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('expense_date', now()->month)
                     ->whereYear('expense_date', now()->year);
    }

    public function scopeThisYear($query)
    {
        return $query->whereYear('expense_date', now()->year);
    }

    public function scopeLast30Days($query)
    {
        return $query->where('expense_date', '>=', now()->subDays(30));
    }

    public function scopeFeed($query)
    {
        return $query->whereHas('category', fn($q) => 
            $q->where('category_name', 'like', '%Feed%')
        );
    }

    public function scopeVeterinary($query)
    {
        return $query->whereHas('category', fn($q) => 
            $q->where('category_name', 'Veterinary')
              ->orWhere('category_name', 'Medicine')
        );
    }

    public function scopeCash($query)
    {
        return $query->where('payment_method', 'Cash');
    }

    public function scopeMpesa($query)
    {
        return $query->where('payment_method', 'M-Pesa');
    }

    public function scopeForAnimal($query, $animalId)
    {
        return $query->where('animal_id', $animalId);
    }

    public function scopeUnapproved($query)
    {
        return $query->whereIn('status', ['Draft', 'Posted']);
    }

    public function scopeOverdue($query)
    {
        return $query->where('is_recurring', true)
                     ->where('projected_next_date', '<', now())
                     ->whereDoesntHave('expenses', fn($q) =>
                         $q->where('expense_date', '>=', \DB::raw('expenses.projected_next_date'))
                     );
    }

    public function scopeHighValue($query, $amount = 100000)
    {
        return $query->where('amount', '>=', $amount);
    }

    public function scopeByVendor($query, $vendor)
    {
        return $query->where('vendor_supplier', 'like', "%{$vendor}%");
    }

    public function scopeTaxDeductible($query)
    {
        return $query->whereHas('category', fn($q) => $q->where('tax_deductible', true));
    }

    public function scopeAnimalHealth($query)
    {
        return $query->whereNotNull('animal_id')
                     ->whereHas('category', fn($q) => 
                         $q->healthRelated()
                     );
    }

    // =================================================================
    // AUTO-APPROVAL & RECURRING LOGIC
    // =================================================================

    protected static function booted()
    {
        static::creating(function ($expense) {
            if (!$expense->status) {
                $expense->status = 'Posted';
            }
            if (!$expense->recorded_by) {
                $expense->recorded_by = auth()->id();
            }
        });

        static::created(function ($expense) {
            if ($expense->is_recurring && $expense->recurring_frequency) {
                $next = match ($expense->recurring_frequency) {
                    'monthly'    => $expense->expense_date->addMonth(),
                    'quarterly'  => $expense->expense_date->addMonths(3),
                    'yearly'     => $expense->expense_date->addYear(),
                    default      => null,
                };
                if ($next) {
                    $expense->update(['projected_next_date' => $next]);
                }
            }
        });
    }

    // =================================================================
    // DASHBOARD-READY QUERIES
    // =================================================================

    public static function monthlyTrend()
    {
        return static::selectRaw('DATE_FORMAT(expense_date, "%Y-%m") as month, SUM(amount) as total')
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('total', 'month');
    }

    public static function topVendors($limit = 5)
    {
        return static::selectRaw('vendor_supplier, SUM(amount) as total')
            ->whereNotNull('vendor_supplier')
            ->groupBy('vendor_supplier')
            ->orderByDesc('total')
            ->limit($limit)
            ->pluck('total', 'vendor_supplier');
    }
}