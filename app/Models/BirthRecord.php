<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Carbon\Carbon;

class BirthRecord extends Model
{
    // =================================================================
    // TABLE CONFIGURATION
    // =================================================================

    protected $table = 'birth_records';
    protected $primaryKey = 'birth_id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = true;

    protected $fillable = [
        'breeding_id',
        'birth_date',
        'birth_time',
        'total_offspring',
        'live_births',
        'stillbirths',
        'birth_type',
        'complications',
        'dam_condition',
        'vet_id',
        'notes',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'birth_time' => 'datetime:H:i',
        'total_offspring' => 'integer',
        'live_births' => 'integer',
        'stillbirths' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // =================================================================
    // CORE RELATIONSHIPS
    // =================================================================

    public function breeding(): BelongsTo
    {
        return $this->belongsTo(BreedingRecord::class, 'breeding_id', 'breeding_id');
    }

    public function dam(): BelongsTo
    {
        return $this->breeding()->first()?->dam();
    }

    public function sire(): BelongsTo
    {
        return $this->breeding()->first()?->sire();
    }

    public function vet(): BelongsTo
    {
        return $this->belongsTo(Veterinarian::class, 'vet_id', 'id');
    }

    public function farmer()
    {
        return $this->dam()?->farmer();
    }

    // =================================================================
    // OFFSPRING & LIVESTOCK
    // =================================================================

    public function offspringRecords(): HasMany
    {
        return $this->hasMany(OffspringRecord::class, 'birth_id', 'birth_id');
    }

    public function offspring(): HasMany
    {
        return $this->hasManyThrough(
            Livestock::class,
            OffspringRecord::class,
            'birth_id',
            'animal_id',
            'birth_id',
            'livestock_id'
        );
    }

    public function liveOffspring(): HasMany
    {
        return $this->offspringRecords()->where('health_status', '!=', 'Deceased');
    }

    // =================================================================
    // PREGNANCY CHECK INTEGRATION
    // =================================================================

    public function pregnancyChecks(): HasMany
    {
        return $this->hasManyThrough(
            PregnancyCheck::class,
            BreedingRecord::class,
            'breeding_id',
            'breeding_id',
            'breeding_id',
            'breeding_id'
        );
    }

    public function latestPregnancyCheck(): ?PregnancyCheck
    {
        return $this->pregnancyChecks()->latest('check_date')->first();
    }

    // =================================================================
    // FINANCIAL & PERFORMANCE
    // =================================================================

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'animal_id', $this->dam()?->animal_id)
                    ->whereBetween('expense_date', [
                        $this->birth_date->subDays(7),
                        $this->birth_date->addDays(30)
                    ]);
    }

    public function incomeFromOffspring(): HasManyThrough
    {
        return $this->hasManyThrough(
            Income::class,
            Livestock::class,
            'animal_id',
            'animal_id',
            null,
            'animal_id'
        )->whereIn('livestock.animal_id', $this->offspring()->pluck('animal_id'));
    }

    // =================================================================
    // POWERFUL ACCESSORS
    // =================================================================

    public function getActualDeliveryDateAttribute(): Carbon
    {
        return Carbon::parse("{$this->birth_date} {$this->birth_time}");
    }

    public function getSuccessRateAttribute(): float
    {
        return $this->total_offspring > 0
            ? round(($this->live_births / $this->total_offspring) * 100, 1)
            : 0;
    }

    public function getHadComplicationsAttribute(): bool
    {
        return !empty($this->complications);
    }

    public function getWasAssistedAttribute(): bool
    {
        return in_array($this->birth_type, ['Assisted', 'Cesarean']);
    }

    public function getHadTwinsAttribute(): bool
    {
        return $this->total_offspring >= 2;
    }

    public function getCalvingEaseScoreAttribute(): string
    {
        return match (true) {
            $this->birth_type === 'Natural' && empty($this->complications) => 'Easy',
            $this->birth_type === 'Assisted' => 'Moderate',
            $this->birth_type === 'Cesarean' || str_contains($this->complications, 'Dystocia') => 'Difficult',
            default => 'Unknown',
        };
    }

    public function getDueDateAccuracyAttribute(): ?int
    {
        $check = $this->latestPregnancyCheck();
        if (!$check?->expected_delivery_date) return null;
        return abs($check->expected_delivery_date->diffInDays($this->birth_date));
    }

    public function getWasOnTimeAttribute(): ?bool
    {
        $accuracy = $this->due_date_accuracy;
        return $accuracy !== null ? $accuracy <= 7 : null;
    }

    public function getCalvingIntervalAttribute(): ?int
    {
        $previous = $this->dam?->birthRecords()
            ->where('birth_id', '<', $this->birth_id)
            ->latest('birth_date')
            ->first();

        if (!$previous) return null;

        return $previous->birth_date->diffInDays($this->birth_date);
    }

    public function getDamAgeAtBirthAttribute(): ?float
    {
        if (!$this->dam?->date_of_birth) return null;
        return round($this->dam->date_of_birth->diffInMonths($this->birth_date) / 12, 1);
    }

    public function getCostOfBirthAttribute(): float
    {
        return $this->expenses()->sum('amount');
    }

    public function getRevenueFromOffspringAttribute(): float
    {
        return $this->incomeFromOffspring()->sum('amount');
    }

    public function getBirthProfitAttribute(): float
    {
        return $this->revenue_from_offspring - $this->cost_of_birth;
    }

    public function getOffspringValuePerHeadAttribute(): ?float
    {
        return $this->live_births > 0
            ? round($this->revenue_from_offspring / $this->live_births, 2)
            : null;
    }

    // =================================================================
    // SCOPES & ALERTS
    // =================================================================

    public function scopeThisYear($query)
    {
        return $query->whereYear('birth_date', now()->year);
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('birth_date', now()->month)
                     ->whereYear('birth_date', now()->year);
    }

    public function scopeWithComplications($query)
    {
        return $query->whereNotNull('complications')
                     ->orWhere('birth_type', '!=', 'Natural');
    }

    public function scopeAssistedOrCsection($query)
    {
        return $query->whereIn('birth_type', ['Assisted', 'Cesarean']);
    }

    public function scopeTwinsOrMore($query)
    {
        return $query->where('total_offspring', '>=', 2);
    }

    public function scopeStillbirths($query)
    {
        return $query->where('stillbirths', '>', 0);
    }

    public function scopeByVet($query, $vetId)
    {
        return $query->where('vet_id', $vetId);
    }

    public function scopeOnTimeDelivery($query)
    {
        return $query->whereHas('pregnancyChecks', function ($q) {
            $q->whereRaw('ABS(DATEDIFF(birth_records.birth_date, pregnancy_checks.expected_delivery_date)) <= 7');
        });
    }

    public function scopeHighValueBirths($query)
    {
        return $query->whereHas('offspring', function ($q) {
            $q->withSum('income', 'amount')
              ->having('income_sum_amount', '>', 50000);
        });
    }

    public function scopeHeifers($query)
    {
        return $query->whereHas('dam', function ($q) {
            $q->whereRaw('TIMESTAMPDIFF(MONTH, date_of_birth, birth_records.birth_date) <= 30');
        });
    }
}