<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientModel extends Model
{
    protected $table = 'clients';

    protected $fillable = [
        'user_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function services()
    {
        return $this->hasMany(ServiceModel::class, 'client_id');
    }

    public function avisEcrits()
    {
        return $this->hasMany(AvisModel::class, 'auteur_id', 'user_id');
    }

    public function avisRecus()
    {
        return $this->hasMany(AvisModel::class, 'cible_id', 'user_id');
    }
}
