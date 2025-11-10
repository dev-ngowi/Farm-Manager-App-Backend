<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class Prescription extends Model
{
    use HasFactory;

    protected $table = 'prescriptions';
    protected $primaryKey = 'prescription_id';

    protected $fillable = [
        'action_id', 'animal_id', 'vet_id', 'farmer_id', 'drug_id',
        'drug_name_custom', 'dosage', 'frequency', 'duration_days',
        'administration_route', 'special_instructions',
        'prescribed_date', 'start_date', 'end_date',
        'withdrawal_period_days', 'quantity_prescribed', 'prescription_status'
    ];

    protected $casts = [
        'prescribed_date' => 'date',
        'start_date' => 'date',
        'end_date' => 'date',
        'duration_days' => 'integer',
        'withdrawal_period_days' => 'integer',
        'quantity_prescribed' => 'decimal:2',
    ];

    // ========================================
    // RELATIONSHIPS
    // ========================================
    public function vetAction(): BelongsTo
    {
        return $this->belongsTo(VetAction::class, 'action_id');
    }

    public function animal(): BelongsTo
    {
        return $this->belongsTo(Livestock::class, 'animal_id');
    }

    public function veterinarian(): BelongsTo
    {
        return $this->belongsTo(Veterinarian::class, 'vet_id');
    }

    public function farmer(): BelongsTo
    {
        return $this->belongsTo(Farmer::class);
    }

    public function drug(): BelongsTo
    {
        return $this->belongsTo(DrugCatalog::class, 'drug_id');
    }

    // ========================================
    // BOOT: Auto-calculate dates
    // ========================================
    protected static function booted()
    {
        static::creating(function ($prescription) {
            if (!$prescription->prescribed_date) {
                $prescription->prescribed_date = now();
            }
            if (!$prescription->start_date) {
                $prescription->start_date = now();
            }
            if (!$prescription->end_date) {
                $prescription->end_date = now()->addDays($prescription->duration_days);
            }
        });

        static::saving(function ($prescription) {
            if ($prescription->isDirty('duration_days') || !$prescription->end_date) {
                $prescription->end_date = Carbon::parse($prescription->start_date)
                    ->addDays($prescription->duration_days);
            }
        });
    }

    // ========================================
    // SCOPES
    // ========================================
    public function scopeActive($query)
    {
        return $query->where('prescription_status', 'Active')
                     ->where('end_date', '>=', today());
    }

    public function scopeExpiringSoon($query, $days = 3)
    {
        return $query->where('prescription_status', 'Active')
                     ->whereBetween('end_date', [today(), today()->addDays($days)]);
    }

    public function scopeForFarmer($query, $farmerId)
    {
        return $query->where('farmer_id', $farmerId);
    }

    // ========================================
    // ACCESSORS — SWAHILI + UI READY
    // ========================================
    public function getDrugNameAttribute(): string
    {
        return $this->drug?->drug_name ?? $this->drug_name_custom ?? 'Dawa Isiyojulikana';
    }

    public function getDosageSwahiliAttribute(): string
    {
        $route = match ($this->administration_route) {
            'Oral' => 'Kunywa',
            'Injection' => 'Sindano',
            'Topical' => 'Kupaka',
            'IV' => 'Mishipani',
            default => $this->administration_route
        };
        return "$this->dosage ($route) - $this->frequency kwa siku $this->duration_days";
    }

    public function getInstructionsSwahiliAttribute(): string
    {
        $lines = [];
        $lines[] = "Dawa: " . $this->getDrugNameAttribute();
        $lines[] = "Kiasi: " . $this->dosage_swahili;
        if ($this->special_instructions) {
            $lines[] = "Maelezo: " . $this->special_instructions;
        }
        if ($this->withdrawal_period_days) {
            $lines[] = "Subiri siku {$this->withdrawal_period_days} kabla ya kuchinja au kutumia maziwa";
        }
        return implode("\n", $lines);
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->prescription_status) {
            'Active' => 'bg-green-100 text-green-800',
            'Completed' => 'bg-blue-100 text-blue-800',
            'Discontinued' => 'bg-red-100 text-red-800',
            default => 'bg-gray-100'
        };
    }

    public function getQrCodeUrlAttribute(): string
    {
        return "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode(route('prescription.show', $this->prescription_id));
    }

    public function getDaysRemainingAttribute(): int
    {
        return max(0, now()->diffInDays($this->end_date, false));
    }

    public function getSmsMessageAttribute(): string
    {
        $animal = $this->animal?->tag ?? 'ng’ombe';
        $drug = $this->getDrugNameAttribute();
        $days = $this->duration_days;
        return "DAWA: $animal amepewa $drug. Tumia mara {$this->frequency} kwa siku $days. Anza leo. Asante!";
    }
}
