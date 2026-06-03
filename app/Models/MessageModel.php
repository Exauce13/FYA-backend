<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessageModel extends Model
{
    protected $table = 'messages';

    protected $fillable = [
        'conversation_id',
        'expediteur_id',
        'destinataire_id',
        'appel_offre_id',
        'content',
        'media',
        'is_read',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
            'read_at' => 'datetime',
            'media' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'expediteur_id');
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
    public function conversation()
    {
        return $this->belongsTo(ConversationModel::class, 'conversation_id');
    }
}
