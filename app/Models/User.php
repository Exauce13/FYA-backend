<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'statut', 'ville', 'quartier', 'telephone', 'photo'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
    public function artisan(){
        return $this->hasOne(ArtisanModel::class, 'user_id');
    }
    public function client(){
        return $this->hasOne(ClientModel::class, 'user_id');
    }
    public function appeloffres(){
        return $this->hasMany(AppelOffreModel::class, 'user_id');
    }
    public function commentaire(){
        return $this->hasMany(CommentaireModel::class, 'user_id');
    }
}
