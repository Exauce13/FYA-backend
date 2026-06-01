<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArtisanModel extends Model
{
    protected $table = 'artisans';

    protected $fillable = [
        'user_id',
        'metiers',
        'bio',
        'npi',
        'annees_experiences',
        'nom_association',
        'telephone_association',
        'diplome',
        'is_certifed',
        'is_boost',
    ];
    public function user(){
        return $this->belongsTo(User::class, 'user_id');
    }
    public function post(){
        return $this->hasMany(PostModel::class, 'artisan_id');
    }

    public function candidatures(){
        return $this->hasMany(CandidatureModel::class, 'artisan_id');
    }

    public function services(){
        return $this->hasMany(ServiceModel::class, 'artisan_id');
    }
}
