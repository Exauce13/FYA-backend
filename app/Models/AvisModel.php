<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AvisModel extends Model
{
    protected $table = 'avis';

    protected $fillable = [
        'auteur_id',
        'cible_id',
        'commentaire',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'note' => 'integer',
        ];
    }

    public function auteur()
    {
        return $this->belongsTo(User::class, 'auteur_id');
    }

    public function cible()
    {
        return $this->belongsTo(User::class, 'cible_id');
    }
}
