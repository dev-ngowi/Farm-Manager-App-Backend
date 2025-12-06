<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Carbon\Carbon;

class HeatCycle extends Model
{
    protected $table = 'heat_cycles';
    protected $primaryKey = 'id'; // now standard

    protected $fillable = [
        'dam_id',
        'observed_date',
        'intensity',
        'notes',
        'next_expected_date',
        'inseminated'
    ];

    protected $casts = [
        'observed_date'      => 'date',
        'next_expected_date' => 'date',
        'inseminated'        => 'boolean',
    ];

    // Relationships
    public function dam(): BelongsTo
    {
        return $this->belongsTo(Livestock::class, 'dam_id', 'animal_id');
    }

    public function insemination(): HasOne
    {
        return $this->hasOne(Insemination::class, 'heat_cycle_id');
    }

    // Accessors
    public function getDaysSinceAttribute(): int
    {
        return $this->observed_date->diffInDays(now());
    }

    public function getIsCurrentAttribute(): bool
    {
        return $this->days_since <= 25;
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('observed_date', '>=', now()->subDays(60));
    }

    public function scopeNotInseminated($query)
    {
        return $query->where('inseminated', false);
    }

    public function scopeExpectedSoon($query, $days = 7)
    {
        return $query->whereBetween('next_expected_date', [now(), now()->addDays($days)]);
    }
}
