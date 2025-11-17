<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class FeedStock extends Model
{
    protected $table = 'feed_stock';
    protected $primaryKey = 'stock_id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'farmer_id',
        'feed_id',
        'batch_number',
        'purchase_date',
        'quantity_purchased_kg',
        'remaining_kg',
        'purchase_price_per_kg',
        'expiry_date',
        'supplier_name',
        'notes',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'expiry_date' => 'date',
        'quantity_purchased_kg' => 'decimal:2',
        'remaining_kg' => 'decimal:2',
        'purchase_price_per_kg' => 'decimal:2',
        'total_cost' => 'decimal:2',
    ];

    // =================================================================
    // RELATIONSHIPS
    // =================================================================
    public function farmer(): BelongsTo
    {
        return $this->belongsTo(Farmer::class, 'farmer_id');
    }

    public function feed(): BelongsTo
    {
        return $this->belongsTo(FeedInventory::class, 'feed_id');
    }

    public function intakeRecords(): HasMany
    {
        return $this->hasMany(FeedIntakeRecord::class, 'stock_id', 'stock_id');
    }

    // =================================================================
    // ACCESSORS
    // =================================================================
    public function getCostPerKgAttribute(): float
    {
        return $this->purchase_price_per_kg;
    }

    public function getIsLowStockAttribute(): bool
    {
        return $this->remaining_kg <= ($this->quantity_purchased_kg * 0.2); // 20%
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->expiry_date && $this->expiry_date->lt(today());
    }

    public function getDaysToExpiryAttribute(): ?int
    {
        return $this->expiry_date?->diffInDays(today(), false);
    }

    public function getStatusAttribute(): string
    {
        if ($this->is_expired) return 'Imepita Muda';
        if ($this->remaining_kg <= 0) return 'Imeisha';
        if ($this->is_low_stock) return 'Inakaribia Kuisha';
        return 'Inapatikana';
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'Imepita Muda' => 'red',
            'Imeisha' => 'gray',
            'Inakaribia Kuisha' => 'orange',
            default => 'green',
        };
    }

    // =================================================================
    // SCOPES
    // =================================================================
    public function scopeAvailable($query)
    {
        return $query->where('remaining_kg', '>', 0)
                     ->where(function ($q) {
                         $q->whereNull('expiry_date')
                           ->orWhere('expiry_date', '>', today());
                     });
    }

    public function scopeLowStock($query, $percent = 20)
    {
        return $query->whereRaw('remaining_kg <= quantity_purchased_kg * ?', [$percent / 100]);
    }

    public function scopeExpiringSoon($query, $days = 30)
    {
        return $query->where('expiry_date', '>', today())
                     ->where('expiry_date', '<=', today()->addDays($days));
    }

    public function scopeByFeed($query, $feedId)
    {
        return $query->where('feed_id', $feedId);
    }
}
