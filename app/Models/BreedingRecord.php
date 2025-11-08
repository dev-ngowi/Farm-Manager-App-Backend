<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class BreedingRecord extends Model
{
    // =================================================================
    // TABLE CONFIGURATION
    // =================================================================

    protected $table = 'breeding_records';
    protected $primaryKey = 'breeding_id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = true;

    protected $fillable = [
        'dam_id',
        'sire_id',
        'breeding_type',
        'ai_semen_code',
        'ai_bull_name',
        'breeding_date',
        'expected_delivery_date',
        'status',
        'notes',
    ];

    protected $casts = [
        'breeding_date'          => 'date',
        'expected_delivery_date' => 'date',
        'created_at'             => 'datetime',
        'updated_at'             => 'datetime',
    ];

    // =================================================================
    // CORE RELATIONSHIPS
    // =================================================================

    public function dam(): BelongsTo
    {
        return $this->belongsTo(Livestock::class, 'dam_id', 'animal_id')
                    ->withDefault(['tag_number' => 'Unknown Dam']);
    }

    public function sire(): BelongsTo
    {
        return $this->belongsTo(Livestock::class, 'sire_id', 'animal_id')
                    ->withDefault(['tag_number' => 'Unknown Sire']);
    }

    public function farmer()
    {
        return $this->dam()->first()?->farmer();
    }

    // =================================================================
    // BIRTH & OFFSPRING
    // =================================================================

    public function birthRecord(): HasOne
    {
        return $this->hasOne(BirthRecord::class, 'breeding_id', 'breeding_id');
    }

    public function offspringRecords(): HasMany
    {
        return $this->hasMany(OffspringRecord::class, 'birth_id')
                    ->join('birth_records', 'offspring_records.birth_id', '=', 'birth_records.birth_id')
                    ->where('birth_records.breeding_id', $this->breeding_id);
    }

    public function offspring(): HasMany
    {
        return $this->hasManyThrough(
            Livestock::class,
            OffspringRecord::class,
            'birth_id', // foreign key on offspring_records (via birth)
            'animal_id', // foreign key on livestock
            null,
            'livestock_id'
        )->join('birth_records', 'offspring_records.birth_id', '=', 'birth_records.birth_id')
         ->where('birth_records.breeding_id', $this->breeding_id);
    }

    // =================================================================
    // FINANCIAL & EFFICIENCY
    // =================================================================

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'animal_id', 'dam_id')
                    ->orWhere('animal_id', $this->sire_id);
    }

    public function income(): HasMany
    {
        return $this->hasManyThrough(
            Income::class,
            Livestock::class,
            'animal_id',
            'animal_id',
            null,
            'animal_id'
        )->whereIn('livestock.animal_id', [$this->dam_id, $this->sire_id]);
    }

    // =================================================================
    // POWERFUL ACCESSORS
    // =================================================================

    public function getDaysPregnantAttribute(): ?int
    {
        if (!$this->breeding_date || $this->status !== 'Confirmed Pregnant') return null;
        return $this->breeding_date->diffInDays(now());
    }

    public function getDaysToDeliveryAttribute(): ?int
    {
        if (!$this->expected_delivery_date) return null;
        return now()->diffInDays($this->expected_delivery_date, false);
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->days_to_delivery < 0 && $this->status === 'Confirmed Pregnant';
    }

    public function getWasSuccessfulAttribute(): bool
    {
        return $this->birthRecord()->exists();
    }

    public function getLiveBirthsAttribute(): int
    {
        return $this->offspringRecords()
            ->where('health_status', '!=', 'Deceased')
            ->count();
    }

    public function getTotalOffspringAttribute(): int
    {
        return $this->offspringRecords()->count();
    }

    public function getSuccessRateAttribute(): ?float
    {
        $total = $this->dam?->breedingAsDam()->count() ?: 1;
        $successes = $this->dam?->breedingAsDam()->whereHas('birthRecord')->count() ?: 0;
        return round(($successes / $total) * 100, 1);
    }

    public function getSireSuccessRateAttribute(): ?float
    {
        $total = $this->sire?->breedingAsSire()->count() ?: 1;
        $successes = $this->sire?->breedingAsSire()->whereHas('birthRecord')->count() ?: 0;
        return round(($successes / $total) * 100, 1);
    }

    public function getCalvingIntervalAttribute(): ?int
    {
        $previous = $this->dam?->breedingAsDam()
            ->where('breeding_id', '<', $this->breeding_id)
            ->whereHas('birthRecord')
            ->latest('expected_delivery_date')
            ->first();

        if (!$previous?->birthRecord?->actual_delivery_date) return null;

        return $previous->birthRecord->actual_delivery_date
            ->diffInDays($this->birthRecord?->actual_delivery_date ?? now());
    }

    public function getCostOfBreedingAttribute(): float
    {
        return $this->expenses()
            ->whereBetween('expense_date', [
                $this->breeding_date->subDays(30),
                $this->expected_delivery_date->addDays(30)
            ])
            ->sum('amount');
    }

    public function getRevenueFromOffspringAttribute(): float
    {
        return $this->offspring()
            ->withSum('income', 'amount')
            ->sum('income_sum_amount') ?? 0;
    }

    public function getBreedingProfitAttribute(): float
    {
        return $this->revenue_from_offspring - $this->cost_of_breeding;
    }

    public function pregnancyChecks(): HasMany
    {
        return $this->hasMany(PregnancyCheck::class, 'breeding_id', 'breeding_id');
    }

    public function latestCheck(): ?PregnancyCheck
    {
        return $this->pregnancyChecks()->latest('check_date')->first();
    }

    public function getConfirmedPregnantAttribute(): bool
    {
        return $this->pregnancyChecks()
            ->where('result', 'Pregnant')
            ->exists();
    }

    public function getLatestDueDateAttribute(): ?Carbon
    {
        return $this->pregnancyChecks()
            ->where('result', 'Pregnant')
            ->max('expected_delivery_date');
    }

    // =================================================================
    // SCOPES & ALERTS
    // =================================================================

    public function scopePregnant($query)
    {
        return $query->where('status', 'Confirmed Pregnant');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'Pending');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'Failed');
    }

    public function scopeDueSoon($query, $days = 7)
    {
        return $query->pregnant()
                     ->whereBetween('expected_delivery_date', [
                         now()->addDays(1),
                         now()->addDays($days)
                     ]);
    }

    public function scopeOverdue($query)
    {
        return $query->pregnant()
                     ->where('expected_delivery_date', '<', now());
    }

    public function scopeAi($query)
    {
        return $query->where('breeding_type', 'AI');
    }

    public function scopeNatural($query)
    {
        return $query->where('breeding_type', 'Natural');
    }

    public function scopeHighSuccessSires($query)
    {
        return $query->whereHas('sire', function ($q) {
            $q->whereHas('breedingAsSire', function ($b) {
                $b->selectRaw('sire_id, COUNT(*) as total, 
                              SUM(CASE WHEN birth_records.breeding_id IS NOT NULL THEN 1 ELSE 0 END) as successes')
                  ->leftJoin('birth_records', 'breeding_records.breeding_id', '=', 'birth_records.breeding_id')
                  ->groupBy('sire_id')
                  ->havingRaw('successes / total >= 0.8');
            });
        });
    }

    public function scopeProfitableBreedings($query)
    {
        return $query->whereHas('offspring', function ($q) {
            $q->withSum('income', 'amount')
              ->withSum('expenses', 'amount')
              ->havingRaw('income_sum_amount > expenses_sum_amount * 1.5');
        });
    }
}