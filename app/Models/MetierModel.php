<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MetierModel extends Model
{
    protected $table = 'metiers';

    protected $fillable = [
        'nom',
    ];

    public function artisans()
    {
        return $this->hasMany(ArtisanModel::class, 'metier_id');
    }
}
