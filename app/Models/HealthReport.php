<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class HealthReport extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $table = 'health_reports';
    protected $primaryKey = 'health_id';

    protected $fillable = [
        'animal_id',
        'farmer_id',
        'symptoms',
        'symptom_onset_date',
        'severity',
        'media_url',
        'report_date',
        'location_latitude',
        'location_longitude',
        'status',
        'priority',
        'notes',
    ];

    protected $casts = [
        'symptom_onset_date' => 'date',
        'report_date' => 'date',
        'location_latitude' => 'decimal:8',
        'location_longitude' => 'decimal:8',
    ];

    // ========================================
    // RELATIONSHIPS
    // ========================================
    public function animal(): BelongsTo
    {
        return $this->belongsTo(Livestock::class, 'animal_id');
    }

    public function farmer(): BelongsTo
    {
        return $this->belongsTo(Farmer::class);
    }

    public function diagnoses()
    {
        return $this->hasMany(HealthDiagnosis::class, 'health_id');
    }

    public function treatments()
    {
        return $this->hasMany(HealthTreatment::class, 'health_id');
    }

    // ========================================
    // MEDIA (Photos/Videos)
    // ========================================
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('health_media')
             ->acceptsMimeTypes(['image/jpeg', 'image/png', 'video/mp4', 'video/3gp'])
             ->useDisk('public');
    }

    public function getMediaUrlsAttribute(): array
    {
        return $this->getMedia('health_media')->map(fn(Media $media) => $media->getUrl())->toArray();
    }

    // ========================================
    // SCOPES
    // ========================================
    public function scopeEmergency($query)
    {
        return $query->where('priority', 'Emergency');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', [
            'Pending Diagnosis',
            'Under Diagnosis',
            'Awaiting Treatment',
            'Under Treatment'
        ]);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('report_date', today());
    }

    public function scopeThisWeek($query)
    {
        return $query->whereBetween('report_date', [now()->startOfWeek(), now()->endOfWeek()]);
    }

    // ========================================
    // ACCESSORS
    // ========================================
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'Pending Diagnosis' => 'gray',
            'Under Diagnosis'   => 'blue',
            'Awaiting Treatment'=> 'orange',
            'Under Treatment'   => 'purple',
            'Recovered'         => 'green',
            'Deceased'          => 'red',
            default             => 'gray',
        };
    }

    public function getPriorityBadgeAttribute(): string
    {
        return match ($this->priority) {
            'Emergency' => 'bg-red-600 text-white',
            'High'      => 'bg-orange-500 text-white',
            'Medium'    => 'bg-yellow-500 text-black',
            'Low'       => 'bg-gray-400 text-white',
            default     => 'bg-gray-300',
        };
    }

    public function getLocationUrlAttribute(): ?string
    {
        if ($this->location_latitude && $this->location_longitude) {
            return "https://maps.google.com/?q={$this->location_latitude},{$this->location_longitude}";
        }
        return null;
    }
}
