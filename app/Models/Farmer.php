<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Builder;

class Farmer extends Model
{
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'user_id',
        'farm_name',
        'farm_purpose',
        'location_id',
        'total_land_acres',
        'years_experience',
        'profile_photo',
    ];

    protected $casts = [
        'total_land_acres' => 'decimal:2',
        'years_experience' => 'integer',
        'profile_photo' => 'string',
    ];

    // =================================================================
    // CORE RELATIONSHIPS
    // =================================================================
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    // =================================================================
    // LIVESTOCK & BREEDING
    // =================================================================
    public function livestock(): HasMany
    {
        return $this->hasMany(Livestock::class, 'farmer_id');
    }

    public function breedingRecords(): HasManyThrough
    {
        return $this->hasManyThrough(
            BreedingRecord::class,
            Livestock::class,
            'farmer_id',
            'dam_id',
            'id',
            'animal_id'
        );
    }

    public function birthRecords(): HasManyThrough
    {
        return $this->hasManyThrough(
            BirthRecord::class,
            Livestock::class,
            'farmer_id',
            'breeding_id',
            'id',
            'animal_id'
        )->join('breeding_records', 'birth_records.breeding_id', '=', 'breeding_records.breeding_id');
    }

    public function offspringRecords(): HasManyThrough
    {
        return $this->hasManyThrough(OffspringRecord::class, BirthRecord::class, 'breeding_id', 'birth_id');
    }

    // =================================================================
    // HEALTH & VETERINARY SERVICES (REAL TABLES FROM SCHEMA)
    // =================================================================
    public function healthReports(): HasMany
    {
        return $this->hasMany(HealthReport::class, 'farmer_id');
    }

    public function vetAppointments(): HasMany
    {
        return $this->hasMany(VetAppointment::class, 'farmer_id');
    }

    public function upcomingVetAppointments(): HasMany
    {
        return $this->vetAppointments()
            ->where('appointment_date', '>=', now())
            ->whereIn('status', ['Scheduled', 'Confirmed']);
    }

    public function chatConversations(): HasMany
    {
        return $this->hasMany(VetChatConversation::class, 'farmer_id');
    }

    public function activeChats(): HasMany
    {
        return $this->chatConversations()->where('status', 'Active');
    }

    public function aiRequests(): HasMany
    {
        return $this->hasMany(AIRequest::class, 'farmer_id');
    }

    public function extensionRequests(): HasMany
    {
        return $this->hasMany(ExtensionServiceRequest::class, 'farmer_id');
    }

    // =================================================================
    // PRODUCTION & EFFICIENCY
    // =================================================================
    public function milkYields(): HasManyThrough
    {
        return $this->hasManyThrough(MilkYieldRecord::class, Livestock::class, 'farmer_id', 'animal_id', 'id', 'animal_id');
    }

    public function weightRecords(): HasManyThrough
    {
        return $this->hasManyThrough(WeightRecord::class, Livestock::class, 'farmer_id', 'animal_id', 'id', 'animal_id');
    }

    public function feedIntakes(): HasManyThrough
    {
        return $this->hasManyThrough(FeedIntakeRecord::class, Livestock::class, 'farmer_id', 'animal_id', 'id', 'animal_id');
    }

    public function productionFactors(): HasManyThrough
    {
        return $this->hasManyThrough(ProductionFactor::class, Livestock::class, 'farmer_id', 'animal_id', 'id', 'animal_id');
    }

    // =================================================================
    // FINANCIAL MODULE
    // =================================================================
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'farmer_id');
    }

    public function income(): HasMany
    {
        return $this->hasMany(Income::class, 'farmer_id');
    }

    // =================================================================
    // DASHBOARD ACCESSORS (FIXED & OPTIMIZED)
    // =================================================================
    public function getFullAddressAttribute(): string
    {
        return $this->location?->full_address ?? 'No location set';
    }

    public function getExperienceLevelAttribute(): string
    {
        return match (true) {
            $this->years_experience >= 10 => 'Veteran',
            $this->years_experience >= 5  => 'Experienced',
            $this->years_experience >= 1  => 'Beginner',
            default                       => 'New Farmer',
        };
    }

    public function getTotalAnimalsAttribute(): int
    {
        return $this->livestock()->count();
    }

    public function getMilkingCowsCountAttribute(): int
    {
        return $this->livestock()
            ->where('sex', 'Female')
            ->where('status', 'Active')
            ->whereHas('milkYields')
            ->count();
    }

    public function getTodayMilkIncomeAttribute(): float
    {
        $milkCategoryId = cache()->remember('milk_sale_category_id', now()->addHours(6), fn() =>
            IncomeCategory::where('category_name', 'Milk Sale')->value('id') ?? 0
        );

        if (!$milkCategoryId) return 0.0;

        return (float) $this->income()
            ->where('category_id', $milkCategoryId)
            ->whereDate('income_date', today())
            ->sum('amount');
    }

    public function getMonthlyProfitAttribute(): float
    {
        $income = $this->income()->thisMonth()->sum('amount');
        $expenses = $this->expenses()->thisMonth()->sum('amount');
        return round($income - $expenses, 2);
    }

    public function getTopEarnerAttribute()
    {
        return $this->livestock()
            ->withSum('income', 'amount')
            ->orderByDesc('income_sum_amount')
            ->first();
    }

    public function getCostliestAnimalAttribute()
    {
        return $this->livestock()
            ->withSum('expenses', 'amount')
            ->orderByDesc('expenses_sum_amount')
            ->first();
    }

    public function getProfilePhotoUrlAttribute(): ?string
    {
        return $this->profile_photo
            ? asset('storage/' . $this->profile_photo)
            : asset('images/default-farmer-avatar.png');
    }

    // NEW: Combined pending requests count (replaces fake serviceRequests)
    public function getPendingRequestsCountAttribute(): int
    {
        return $this->healthReports()->where('status', 'Pending Diagnosis')->count()
             + $this->vetAppointments()->where('status', 'Scheduled')->count()
             + $this->aiRequests()->where('status', 'Pending')->count()
             + $this->extensionRequests()->where('status', 'Pending')->count();
    }

    // =================================================================
    // SCOPES
    // =================================================================
    public function scopeLargeScale(Builder $query): Builder
    {
        return $query->where('total_land_acres', '>=', 50);
    }

    public function scopeDairyFocused(Builder $query): Builder
    {
        return $query->where('farm_purpose', 'like', '%Dairy%');
    }
}
