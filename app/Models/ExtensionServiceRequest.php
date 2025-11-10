<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExtensionServiceRequest extends Model
{
    protected $table = 'extension_service_requests';
    protected $primaryKey = 'request_id';

    protected $fillable = [
        'farmer_id', 'service_type', 'description', 'preferred_date',
        'status', 'officer_notes', 'assigned_officer_id'
    ];

    public function farmer()
    {
        return $this->belongsTo(Farmer::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'Pending');
    }
}
