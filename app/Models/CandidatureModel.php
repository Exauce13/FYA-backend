<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CandidatureModel extends Model
{
    protected $table = 'candidatures';

    protected $appends = ['devis_url'];

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

    public function getDevisUrlAttribute(): ?string
    {
        return $this->devis_propose ? Storage::url($this->devis_propose) : null;
    }
}
