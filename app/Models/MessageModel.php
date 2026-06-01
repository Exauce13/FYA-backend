<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessageModel extends Model
{
    protected $table = 'messages';

    protected $fillable = [
        'expediteur_id',
        'destinataire_id',
        'appel_offre_id',
        'content',
        'file_url',
        'is_read',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
            'read_at' => 'datetime',
        ];
    }

    public function expediteur()
    {
        return $this->belongsTo(User::class, 'expediteur_id');
    }

    public function destinataire()
    {
        return $this->belongsTo(User::class, 'destinataire_id');
    }

    public function appelOffre()
    {
        return $this->belongsTo(AppelOffreModel::class, 'appel_offre_id');
    }
}
