<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AIRequest extends Model
{
    protected $table = 'ai_requests'; // EXACT TABLE NAME
    protected $primaryKey = 'request_id';

    protected $fillable = [
        'farmer_id',
        'animal_id',
        'preferred_date',
        'preferred_time',
        'status',
        'notes',
        'assigned_vet_id',
        'assigned_date',
    ];

    protected $casts = [
        'preferred_date' => 'date',
        'preferred_time' => 'datetime:H:i',
        'assigned_date' => 'date',
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

    public function vet(): BelongsTo
    {
        return $this->belongsTo(Veterinarian::class, 'assigned_vet_id');
    }

    // =================================================================
    // SCOPES
    // =================================================================
    public function scopePending($query)
    {
        return $query->where('status', 'Pending');
    }

    public function scopeAssigned($query)
    {
        return $query->where('status', 'Assigned');
    }

    public function scopeToday($query)
    {
        return $query->whereDate('preferred_date', today());
    }
}
