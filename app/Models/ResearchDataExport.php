<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Carbon\Carbon;

class ResearchDataExport extends Model
{
    use HasFactory;

    protected $table = 'research_data_exports';
    protected $primaryKey = 'export_id';

    protected $fillable = [
        'request_id', 'researcher_id', 'export_format', 'file_name',
        'file_size_kb', 'file_path', 'download_url', 'record_count',
        'anonymization_applied', 'export_date', 'download_count',
        'last_downloaded_at', 'expires_at'
    ];

    protected $casts = [
        'export_date' => 'date',
        'expires_at' => 'datetime',
        'last_downloaded_at' => 'datetime',
        'anonymization_applied' => 'boolean',
    ];

    // ========================================
    // RELATIONSHIPS
    // ========================================
    public function dataRequest(): BelongsTo
    {
        return $this->belongsTo(ResearchDataRequest::class, 'request_id');
    }

    public function researcher(): BelongsTo
    {
        return $this->belongsTo(Researcher::class);
    }

    // ========================================
    // ACCESSORS — SWAHILI + UI READY
    // ========================================
    public function getFormatSwahiliAttribute(): string
    {
        return match ($this->export_format) {
            'CSV' => 'CSV (Excel)',
            'Excel' => 'Excel',
            'JSON' => 'JSON',
            'PDF' => 'PDF',
            'SQL' => 'SQL Database',
            default => $this->export_format
        };
    }

    public function getSizeMbAttribute(): ?string
    {
        if (!$this->file_size_kb) return null;
        $mb = round($this->file_size_kb / 1024, 2);
        return "$mb MB";
    }

    public function getStatusBadgeAttribute(): string
    {
        if ($this->isExpired()) return 'bg-red-100 text-red-800';
        if ($this->download_count > 0) return 'bg-green-100 text-green-800';
        return 'bg-blue-100 text-blue-800';
    }

    public function getStatusTextAttribute(): string
    {
        if ($this->isExpired()) return 'Imeisha Muda';
        if ($this->download_count > 0) return "Imepakuliwa mara {$this->download_count}";
        return 'Inasubiri Kupakuliwa';
    }

    public function getSecureDownloadUrlAttribute(): ?string
    {
        if (!$this->file_path || $this->isExpired()) return null;

        return URL::temporarySignedRoute(
            'research.export.download',
            now()->addDays(7),
            ['export' => $this->export_id]
        );
    }

    public function getExpiryTextAttribute(): string
    {
        if (!$this->expires_at) return 'Haina Muda';
        $days = now()->diffInDays($this->expires_at, false);
        if ($days < 0) return 'Imeisha';
        if ($days == 0) return 'Leo';
        return "Siku $days zijazo";
    }

    // ========================================
    // METHODS
    // ========================================
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function incrementDownload(): void
    {
        $this->increment('download_count');
        $this->update(['last_downloaded_at' => now()]);
    }

    public function deleteFile(): void
    {
        if ($this->file_path && Storage::exists($this->file_path)) {
            Storage::delete($this->file_path);
        }
    }

    public static function boot()
    {
        parent::boot();

        static::deleting(function ($export) {
            $export->deleteFile();
        });
    }
}
