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
    ];

    protected $attributes = [
        'statut_plainte' => self::STATUT_EN_ATTENTE,
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
}
