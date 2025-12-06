<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lactation extends Model
{
    protected $table = 'lactations';
    protected $primaryKey = 'lactation_id';

    protected $fillable = [
        'dam_id',
        'lactation_number',
        'start_date',
        'peak_date',
        'dry_off_date',
        'total_milk_kg',
        'days_in_milk',
        'status'
    ];

    protected $casts = [
        'start_date'    => 'date',
        'peak_date'     => 'date',
        'dry_off_date'  => 'date',
    ];

    public function dam(): BelongsTo
    {
        return $this->belongsTo(Livestock::class, 'dam_id', 'animal_id');
    }

    public function milkYields(): HasMany
    {
        return $this->hasMany(MilkYieldRecord::class, 'lactation_id');
    }

    public function getAverageDailyMilkAttribute(): float
    {
        return $this->days_in_milk > 0 ? round($this->total_milk_kg / $this->days_in_milk, 2) : 0;
    }

    public function getIsCurrentAttribute(): bool
    {
        return $this->status === 'Ongoing';
    }
}
