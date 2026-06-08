<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppelOffreModel extends Model
{
    protected $table = "appels_offres";
    protected $fillable = ['user_id', 'titre', 'description', 'metier_id', 'ville', 'budget', 'appel_json', 'status'];


    protected function casts(): array
    {
        return [
            'budget' => 'integer',
            'appel_json' => 'array',
        ];
    }

    public function user(){
        return $this->belongsTo(User::class, 'user_id');
    }

    public function metier()
    {
        return $this->belongsTo(MetierModel::class, 'metier_id');
    }

    public function candidatures(){
        return $this->hasMany(CandidatureModel::class, 'appeloffer_id');
    }
}
