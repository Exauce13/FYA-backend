<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArtisanModel extends Model
{
    protected $table = 'artisans';

    protected $fillable = [
        'user_id',
        'metier_id',
        'bio',
        'nom_atelier',
        'npi',
        'annees_experiences',
        'piece_identites',
        'nom_association',
        'telephone_association',
        'diplome',
        'is_certifed',
        'is_boost',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function metier()
    {
        return $this->belongsTo(MetierModel::class, 'metier_id');
    }

    public function post()
    {
        return $this->hasMany(PostModel::class, 'artisan_id');
    }

    public function candidatures()
    {
        return $this->hasMany(CandidatureModel::class, 'artisan_id');
    }

    public function services()
    {
        return $this->hasMany(ServiceModel::class, 'artisan_id');
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
