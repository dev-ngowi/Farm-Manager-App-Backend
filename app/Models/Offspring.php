<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Offspring extends Model
{
    protected $table = 'offspring';
    protected $primaryKey = 'id';

    protected $fillable = [
        'delivery_id',
        'temporary_tag',
        'gender',
        'birth_weight_kg',
        'birth_condition',
        'colostrum_intake',
        'navel_treated',
        'livestock_id',
        'notes'
    ];

    protected $casts = [
        'birth_weight_kg' => 'decimal:2',
        'navel_treated'   => 'boolean',
    ];

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    public function livestock(): BelongsTo
    {
        return $this->belongsTo(Livestock::class, 'livestock_id', 'animal_id');
    }

    public function dam()
    {
        return $this->delivery->dam;
    }

    public function sire()
    {
        return $this->delivery->insemination->sire ?? $this->delivery->insemination->semen->bull;
    }

    // Helper: Register calf as full livestock
    public function registerAsLivestock(array $data = [])
    {
        $animal = Livestock::create(array_merge([
            'tag_number'     => $this->temporary_tag ?? 'CALF-' . $this->id,
            'name'          => null,
            'sex'           => $this->gender,
            'date_of_birth'  => $this->delivery->actual_delivery_date,
            'weight_at_birth_kg' => $this->birth_weight_kg,
            'dam_id'        => $this->dam()->animal_id,
            'sire_id'       => $this->sire()?->animal_id,
            'status'         => 'Active',
        ], $data));

        $this->update(['livestock_id' => $animal->animal_id]);

        return $animal;
    }
}
