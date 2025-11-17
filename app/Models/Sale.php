<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Sale extends Model
{
    use SoftDeletes;

    protected $table = 'sales';
    protected $primaryKey = 'sale_id';
    public $timestamps = true;

    protected $fillable = [
        'farmer_id',
        'animal_id',
        'sale_type',
        'buyer_name',
        'buyer_phone',
        'buyer_location',
        'quantity',
        'unit',
        'unit_price',
        'total_amount',
        'sale_date',
        'payment_method',
        'receipt_number',
        'notes',
        'status',
    ];

    protected $casts = [
        'sale_date' => 'date',
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'deleted_at' => 'datetime',
    ];

    // =================================================================
    // RELATIONSHIPS
    // =================================================================
    public function farmer(): BelongsTo
    {
        return $this->belongsTo(Farmer::class, 'farmer_id');
    }

    public function animal(): BelongsTo
    {
        return $this->belongsTo(Livestock::class, 'animal_id');
    }

    // =================================================================
    // ACCESSORS
    // =================================================================
    public function getFormattedTotalAttribute(): string
    {
        return 'TZS ' . number_format($this->total_amount, 2);
    }

    public function getIsAnimalSaleAttribute(): bool
    {
        return $this->sale_type === 'Animal';
    }

    public function getIsMilkSaleAttribute(): bool
    {
        return $this->sale_type === 'Milk';
    }

    public function getIsCashAttribute(): bool
    {
        return $this->payment_method === 'Cash';
    }

    // =================================================================
    // SCOPES
    // =================================================================
    public function scopeThisMonth($query)
    {
        return $query->whereMonth('sale_date', now()->month)
                     ->whereYear('sale_date', now()->year);
    }

    public function scopeThisYear($query)
    {
        return $query->whereYear('sale_date', now()->year);
    }

    public function scopeAnimalSales($query)
    {
        return $query->where('sale_type', 'Animal');
    }

    public function scopeMilkSales($query)
    {
        return $query->where('sale_type', 'Milk');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'Completed');
    }

    public function scopeByType($query, $type)
    {
        return $query->where('sale_type', $type);
    }
}
