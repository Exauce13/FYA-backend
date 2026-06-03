<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceModel extends Model
{
    protected $table = 'services';

    protected $fillable = [
        'client_id',
        'artisan_id',
        'message_id',
        'appeloffer_id',
        'titre',
        'description',
        'montant',
        'duree_service',
        'statut',
        'devis',
        'client_lu_at',
        'client_valide_at',
        'artisan_termine_at',
        'client_termine_at',
    ];

    protected function casts(): array
    {
        return [
            'client_lu_at' => 'datetime',
            'client_valide_at' => 'datetime',
            'artisan_termine_at' => 'datetime',
            'client_termine_at' => 'datetime',
        ];
    }

    public function client()
    {
        return $this->belongsTo(ClientModel::class, 'client_id');
    }

    public function artisan()
    {
        return $this->belongsTo(ArtisanModel::class, 'artisan_id');
    }

    public function message()
    {
        return $this->belongsTo(MessageModel::class, 'message_id');
    }

    public function appelOffre()
    {
        return $this->belongsTo(AppelOffreModel::class, 'appeloffer_id');
    }

    public function avis()
    {
        return $this->hasMany(AvisModel::class, 'service_id');
    }
}
