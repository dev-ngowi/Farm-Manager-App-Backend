<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DataRequestCategory extends Model
{
    use HasFactory;

    protected $table = 'data_request_categories';
    protected $primaryKey = 'category_id';

    protected $fillable = [
        'category_name',
        'description',
        'requires_special_approval'
    ];

    protected $casts = [
        'requires_special_approval' => 'boolean',
    ];

    // ========================================
    // SCOPES
    // ========================================
    public function scopeRequiresSpecialApproval($query)
    {
        return $query->where('requires_special_approval', true);
    }

    public function scopeStandard($query)
    {
        return $query->where('requires_special_approval', false);
    }

    // ========================================
    // ACCESSORS — SWAHILI + UI READY
    // ========================================
    public function getNameSwahiliAttribute(): string
    {
        return match ($this->category_name) {
            'Livestock Demographics' => 'Takwimu za Mifugo',
            'Health Records' => 'Rekodi za Afya',
            'Disease Outbreaks' => 'Milipuko ya Magonjwa',
            'Vaccination Records' => 'Rekodi za Chanjo',
            'Breeding Data' => 'Data ya Uzazi',
            'Milk Production' => 'Maziwa Yanayozalishwa',
            'Weight & Growth' => 'Uzito na Ukuaji',
            'Mortality Records' => 'Vifo vya Wanyama',
            'Treatment History' => 'Historia ya Matibabu',
            'Geolocation Data' => 'Data ya Mahali',
            'Farmer Profiles' => 'Profaili za Wakulima',
            'Market Prices' => 'Bei za Soko',
            'Climate Data' => 'Data ya Hali ya Hewa',
            default => $this->category_name
        };
    }

    public function getApprovalBadgeAttribute(): string
    {
        return $this->requires_special_approval
            ? 'bg-red-100 text-red-800'
            : 'bg-green-100 text-green-800';
    }

    public function getApprovalTextAttribute(): string
    {
        return $this->requires_special_approval
            ? 'Inahitaji Idhini Maalum'
            : 'Idhini ya Kawaida';
    }

    public function getIconAttribute(): string
    {
        return match ($this->category_name) {
            'Livestock Demographics' => 'Users',
            'Health Records' => 'Heart Pulse',
            'Disease Outbreaks' => 'Alert Triangle',
            'Vaccination Records' => 'Syringe',
            'Breeding Data' => 'Dna',
            'Milk Production' => 'Milk',
            'Weight & Growth' => 'Trending Up',
            'Mortality Records' => 'Skull',
            'Treatment History' => 'Pill',
            'Geolocation Data' => 'Map Pin',
            'Farmer Profiles' => 'User Check',
            'Market Prices' => 'Dollar Sign',
            'Climate Data' => 'Cloud Rain',
            default => 'Database'
        };
    }

    public function getDescriptionShortAttribute(): string
    {
        return \Str::limit(strip_tags($this->description), 100);
    }
}
