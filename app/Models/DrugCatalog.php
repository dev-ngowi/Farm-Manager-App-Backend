<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class DrugCatalog extends Model
{
    use HasFactory, Searchable; 

    protected $table = 'drug_catalog';
    protected $primaryKey = 'drug_id';

    protected $fillable = [
        'drug_name',
        'generic_name',
        'drug_category',
        'manufacturer',
        'common_dosage',
        'withdrawal_period_days',
        'side_effects',
        'contraindications',
        'storage_conditions',
        'is_prescription_only',
    ];

    protected $casts = [
        'is_prescription_only' => 'boolean',
        'withdrawal_period_days' => 'integer',
    ];

    // ========================================
    // SEARCHABLE (Scout)
    // ========================================
    public function toSearchableArray(): array
    {
        return [
            'drug_id' => $this->drug_id,
            'drug_name' => $this->drug_name,
            'generic_name' => $this->generic_name,
            'drug_category' => $this->drug_category,
            'manufacturer' => $this->manufacturer,
        ];
    }

    // ========================================
    // SCOPES
    // ========================================
    public function scopePrescriptionOnly($query)
    {
        return $query->where('is_prescription_only', true);
    }

    public function scopeOverTheCounter($query)
    {
        return $query->where('is_prescription_only', false);
    }

    public function scopeAntibiotics($query)
    {
        return $query->where('drug_category', 'like', '%Antibiotic%');
    }

    public function scopeAntiparasitic($query)
    {
        return $query->where('drug_category', 'like', '%Antiparasitic%');
    }

    // ========================================
    // ACCESSORS
    // ========================================
    public function getCategorySwahiliAttribute(): string
    {
        return match ($this->drug_category) {
            'Antibiotic' => 'Antibayotiki',
            'Antiparasitic' => 'Dawa ya Minyoo',
            'Anti-inflammatory' => 'Dawa ya Kuvimba',
            'Vaccine' => 'Chanjo',
            'Vitamin' => 'Vitamini',
            'Dewormer' => 'Dawa ya Minyoo',
            'Pain Relief' => 'Dawa ya Maumivu',
            default => $this->drug_category ?? 'Haina Jamii'
        };
    }

    public function getPrescriptionBadgeAttribute(): string
    {
        return $this->is_prescription_only
            ? 'bg-red-100 text-red-800'
            : 'bg-green-100 text-green-800';
    }

    public function getPrescriptionTextAttribute(): string
    {
        return $this->is_prescription_only
            ? 'Inahitaji Dawa ya Daktari'
            : 'Inauzwa Bila Dawa';
    }

    public function getWithdrawalTextAttribute(): string
    {
        if (!$this->withdrawal_period_days) return 'Hakuna Muda wa Kusubiri';
        $days = $this->withdrawal_period_days;
        return "Subiri siku $days kabla ya kuchinja au kutumia maziwa";
    }

    public function getStorageSwahiliAttribute(): string
    {
        if (!$this->storage_conditions) return 'Hifadhi mahali pazuri';

        $map = [
            'refrigerate' => 'Weka kwenye friji (2-8°C)',
            'room temperature' => 'Weka mahali pasipo na jua',
            'cool dry place' => 'Mahali penye baridi na kavu',
            'protect from light' => 'Epuka mwanga wa jua',
        ];

        $lower = strtolower($this->storage_conditions);
        foreach ($map as $key => $swahili) {
            if (str_contains($lower, $key)) return $swahili;
        }
        return $this->storage_conditions;
    }

    public function getDosageShortAttribute(): string
    {
        if (!$this->common_dosage) return 'Angalia maelekezo';
        return str_replace(['per kg', 'kg'], ['kwa kila kilo', 'kilo'], $this->common_dosage);
    }
}
