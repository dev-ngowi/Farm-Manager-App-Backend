<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Delivery extends Model
{
    protected $table = 'deliveries';
    protected $primaryKey = 'id';

    protected $fillable = [
        'insemination_id',
        'actual_delivery_date',
        'delivery_type',
        'calving_ease_score',
        'total_born',
        'live_born',
        'stillborn',
        'dam_condition_after',
        'notes'
    ];

    protected $casts = [
        'actual_delivery_date' => 'date',
    ];

    public function insemination(): BelongsTo
    {
        return $this->belongsTo(Insemination::class);
    }

    public function dam()
    {
        return $this->insemination->dam;
    }

    public function offspring(): HasMany
    {
        return $this->hasMany(Offspring::class, 'delivery_id');
    }

    public function getCalvingIntervalAttribute(): ?int
    {
        $prev = Delivery::whereHas('insemination.dam', fn($q) => $q->where('animal_id', $this->dam->animal_id))
            ->where('id', '<', $this->id)
            ->latest('actual_delivery_date')
            ->first();

        return $prev ? $prev->actual_delivery_date->diffInDays($this->actual_delivery_date) : null;
    }
}
