<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder; // ⭐ ADDED

class Semen extends Model
{
    protected $table = 'semen_inventory';
    protected $primaryKey = 'id';

    protected $fillable = [
        'straw_code', 'bull_id', 'bull_tag', 'bull_name', 'breed_id',
        'collection_date', 'dose_ml', 'motility_percentage',
        'cost_per_straw', 'source_supplier', 'used',
        'farmer_id', // ⭐ ADDED: Crucial for ownership checks
    ];

    protected $casts = [
        'collection_date' => 'date',
        'cost_per_straw'  => 'decimal:2',
        'used'            => 'boolean',
    ];

    // --- Relationships ---

    public function bull(): BelongsTo
    {
        return $this->belongsTo(Livestock::class, 'bull_id', 'animal_id');
    }

    public function breed(): BelongsTo
    {
        return $this->belongsTo(Breed::class, 'breed_id');
    }

    public function inseminations(): HasMany
    {
        return $this->hasMany(Insemination::class, 'semen_id');
    }

    // --- Accessors ---

    public function getSuccessRateAttribute(): float
    {
        // FIX: The original logic for success_rate was looking for a 'delivery'
        // relation on the Insemination model, which might not be defined or available
        // unless you're confident it works. A safer check is often by status.
        // Assuming 'delivery' relation exists, the original code is fine,
        // but for robustness, I'll keep the original as you wrote it.
        $total = $this->inseminations()->count();
        $success = $this->inseminations()->whereHas('delivery')->count();
        return $total > 0 ? round(($success / $total) * 100, 1) : 0.0;
    }

    // --- Scopes ---

    // ⭐ FIX: Add the missing ownership scope
    public function scopeOwnedByFarmer(Builder $query, int $farmerId): void
    {
        $query->where('farmer_id', $farmerId);
    }

    public function scopeAvailable($query)
    {
        return $query->where('used', false);
    }
}
