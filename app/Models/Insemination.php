<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Insemination extends Model
{
    protected $table = 'inseminations';
    protected $primaryKey = 'id';

    protected $fillable = [
        'dam_id',
        'sire_id',
        'semen_id',
        'heat_cycle_id',
        'technician_id',
        'breeding_method',
        'insemination_date',
        'expected_delivery_date',
        'status',
        'notes'
    ];

    protected $casts = [
        'insemination_date'      => 'date',
        'expected_delivery_date' => 'date',
    ];

    // Relationships
    public function dam(): BelongsTo
    {
        return $this->belongsTo(Livestock::class, 'dam_id', 'animal_id');
    }

    public function sire(): BelongsTo
    {
        return $this->belongsTo(Livestock::class, 'sire_id', 'animal_id');
    }

    public function semen(): BelongsTo
    {
        return $this->belongsTo(Semen::class);
    }

    public function heatCycle(): BelongsTo
    {
        return $this->belongsTo(HeatCycle::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function pregnancyChecks(): HasMany
    {
        return $this->hasMany(PregnancyCheck::class, 'insemination_id');
    }

    public function delivery(): HasOne
    {
        return $this->hasOne(Delivery::class, 'insemination_id');
    }

    public function offspring()
    {
        return $this->hasManyThrough(Offspring::class, Delivery::class, 'insemination_id', 'delivery_id');
    }

    // Smart Accessors
    public function getIsPregnantAttribute(): bool
    {
        return $this->pregnancyChecks()->where('result', 'Pregnant')->exists();
    }

    public function getDaysPregnantAttribute(): ?int
    {
        return $this->is_pregnant ? $this->insemination_date->diffInDays(now()) : null;
    }

    public function getDaysToDueAttribute(): ?int
    {
        return $this->expected_delivery_date ? now()->diffInDays($this->expected_delivery_date, false) : null;
    }

    public function getWasSuccessfulAttribute(): bool
    {
        return $this->delivery()->exists();
    }
}
