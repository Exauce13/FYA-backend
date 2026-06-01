<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CandidatureModel extends Model
{
    protected $table = 'candidatures';

    protected $fillable = [
        'appeloffer_id',
        'artisan_id',
        'description',
        'devis_propose',
        'statut',
    ];

    public function appelOffre()
    {
        return $this->belongsTo(AppelOffreModel::class, 'appeloffer_id');
    }

    public function artisan()
    {
        return $this->belongsTo(ArtisanModel::class, 'artisan_id');
    }
}
