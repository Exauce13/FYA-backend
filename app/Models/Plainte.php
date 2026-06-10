<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plainte extends Model
{
    public const STATUT_EN_ATTENTE = 'en_attente';
    public const STATUT_GEREE = 'geree';

    protected $table = 'plaintes';

    protected $fillable = [
        'plaignant_id',
        'mise_en_cause_id',
        'motif',
        'description',
        'statut_plainte',
        'admin_status',
    ];

    protected $attributes = [
        'statut_plainte' => self::STATUT_EN_ATTENTE,
        'admin_status' => self::STATUT_EN_ATTENTE,
    ];

    protected function casts(): array
    {
        return [
            'plaignant_id' => 'integer',
            'mise_en_cause_id' => 'integer',
        ];
    }

    public function plaignant()
    {
        return $this->belongsTo(User::class, 'plaignant_id');
    }

    public function miseEnCause()
    {
        return $this->belongsTo(User::class, 'mise_en_cause_id');
    }

    public function resolveRouteBinding($value, $field = null)
    {
        if (str_starts_with((string) $value, 'REP-')) {
            $value = substr((string) $value, 4);
        }

        return $this->query()->where($field ?? $this->getRouteKeyName(), $value)->first();
    }
}
