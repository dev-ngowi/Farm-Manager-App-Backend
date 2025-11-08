<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Carbon\Carbon;

class OffspringRecord extends Model
{
    // =================================================================
    // TABLE CONFIGURATION
    // =================================================================

    protected $table = 'offspring_records';
    protected $primaryKey = 'offspring_id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = true;

    protected $fillable = [
        'birth_id',
        'animal_tag',
        'gender',
        'weight_at_birth_kg',
        'health_status',
        'colostrum_intake',
        'registered_as_livestock',
        'livestock_id',
        'notes',
    ];

    protected $casts = [
        'weight_at_birth_kg'       => 'decimal:2',
        'registered_as_livestock' => 'boolean',
        'created_at'               => 'datetime',
        'updated_at'               => 'datetime',
    ];

    // =================================================================
    // CORE RELATIONSHIPS
    // =================================================================

    public function birth(): BelongsTo
    {
        return $this->belongsTo(BirthRecord::class, 'birth_id', 'birth_id');
    }

    public function dam(): BelongsTo
    {
        return $this->birth()->first()?->dam();
    }

    public function sire(): BelongsTo
    {
        return $this->birth()->first()?->sire();
    }

    public function livestock(): BelongsTo
    {
        return $this->belongsTo(Livestock::class, 'livestock_id', 'animal_id')
                    ->withDefault();
    }

    public function farmer()
    {
        return $this->dam()?->farmer();
    }

    // =================================================================
    // PRODUCTION & GROWTH TRACKING
    // =================================================================

    public function weightRecords(): HasManyThrough
    {
        return $this->hasManyThrough(
            WeightRecord::class,
            Livestock::class,
            'animal_id',
            'animal_id',
            'livestock_id',
            'animal_id'
        );
    }

    public function milkYields(): HasManyThrough
    {
        return $this->hasManyThrough(
            MilkYieldRecord::class,
            Livestock::class,
            'animal_id',
            'animal_id',
            'livestock_id',
            'animal_id'
        );
    }

    public function latestWeight(): ?WeightRecord
    {
        return $this->weightRecords()->latest('record_date')->first();
    }

    // =================================================================
    // FINANCIAL INTEGRATION
    // =================================================================

    public function income(): HasManyThrough
    {
        return $this->hasManyThrough(
            Income::class,
            Livestock::class,
            'animal_id',
            'animal_id',
            'livestock_id',
            'animal_id'
        );
    }

    public function expenses(): HasManyThrough
    {
        return $this->hasManyThrough(
            Expense::class,
            Livestock::class,
            'animal_id',
            'animal_id',
            'livestock_id',
            'animal_id'
        );
    }

    // =================================================================
    // POWERFUL ACCESSORS
    // =================================================================

    public function getIsRegisteredAttribute(): bool
    {
        return $this->registered_as_livestock && $this->livestock()->exists();
    }

    public function getNeedsRegistrationAttribute(): bool
    {
        return !$this->is_registered && in_array($this->health_status, ['Healthy', 'Weak']);
    }

    public function getAgeInDaysAttribute(): int
    {
        return $this->birth?->birth_date
            ? $this->birth->birth_date->diffInDays(now())
            : 0;
    }

    public function getCurrentWeightAttribute(): ?float
    {
        return $this->latestWeight?->weight_kg;
    }

    public function getAdgSinceBirthAttribute(): ?float
    {
        if (!$this->current_weight || $this->age_in_days <= 0) return null;
        $gain = $this->current_weight - ($this->weight_at_birth_kg ?? 0);
        return round($gain / $this->age_in_days, 3);
    }

    public function getColostrumStatusAttribute(): string
    {
        return match ($this->colostrum_intake) {
            'Adequate'     => 'Good',
            'Partial'      => 'Risk',
            'Insufficient' => 'High Risk',
            'None'         => 'Critical',
            default        => 'Unknown',
        };
    }

    public function getSurvivalStatusAttribute(): string
    {
        if ($this->health_status === 'Deceased') return 'Died';
        if ($this->age_in_days < 30) return 'Neonatal';
        if ($this->age_in_days < 180) return 'Weaning';
        return 'Survived';
    }

    public function getMarketValueEstimateAttribute(): ?float
    {
        if (!$this->current_weight) return null;
        $pricePerKg = $this->gender === 'Male' ? 480 : 520; // Bull vs Heifer
        return round($this->current_weight * $pricePerKg, 2);
    }

    public function getTotalRevenueAttribute(): float
    {
        return $this->income()->sum('amount');
    }

    public function getTotalCostAttribute(): float
    {
        return $this->expenses()->sum('amount') + 
               ($this->dam?->purchase_cost ?? 0) * 0.1; // 10% dam cost allocation
    }

    public function getNetProfitAttribute(): float
    {
        return $this->total_revenue - $this->total_cost;
    }

    public function getIsTwinAttribute(): bool
    {
        return $this->birth?->total_offspring >= 2;
    }

    public function getTwinSurvivalBonusAttribute(): ?string
    {
        if (!$this->is_twin) return null;
        $twin = $this->birth->offspringRecords()
            ->where('offspring_id', '!=', $this->offspring_id)
            ->first();
        if (!$twin) return 'Only One Survived';
        return $twin->health_status === 'Healthy' ? 'Both Survived' : 'One Survived';
    }

    // =================================================================
    // SCOPES & ALERTS
    // =================================================================

    public function scopeUnregistered($query)
    {
        return $query->where('registered_as_livestock', false)
                     ->orWhereNull('livestock_id');
    }

    public function scopeHealthy($query)
    {
        return $query->where('health_status', 'Healthy');
    }

    public function scopeWeak($query)
    {
        return $query->where('health_status', 'Weak');
    }

    public function scopeDeceased($query)
    {
        return $query->where('health_status', 'Deceased');
    }

    public function scopeNeedsColostrum($query)
    {
        return $query->whereIn('colostrum_intake', ['Insufficient', 'None', 'Partial']);
    }

    public function scopeCritical($query)
    {
        return $query->where('colostrum_intake', 'None')
                     ->orWhere('health_status', 'Weak');
    }

    public function scopeMale($query)
    {
        return $query->where('gender', 'Male');
    }

    public function scopeFemale($query)
    {
        return $query->where('gender', 'Female');
    }

    public function scopeTwins($query)
    {
        return $query->whereHas('birth', fn($q) => $q->where('total_offspring', '>=', 2));
    }

    public function scopeHighValue($query)
    {
        return $query->where('weight_at_birth_kg', '>', 40)
                     ->orWhereHas('livestock', fn($q) => 
                         $q->where('current_weight_kg', '>', 300)
                     );
    }

    public function scopeReadyForWeaning($query)
    {
        return $query->whereHas('birth', fn($q) => 
            $q->where('birth_date', '<=', now()->subDays(180))
        );
    }

    public function scopeProfitableCalves($query)
    {
        return $query->whereHas('livestock', function ($q) {
            $q->withSum('income', 'amount')
              ->withSum('expenses', 'amount')
              ->havingRaw('income_sum_amount > expenses_sum_amount * 1.2');
        });
    }
}