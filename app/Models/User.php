<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
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
    public function avisEcrits()
    {
        return $this->hasMany(AvisModel::class, 'auteur_id');
    }
    public function avisRecus()
    {
        return $this->hasMany(AvisModel::class, 'cible_id');
    }
    public function plaintesDeposees()
    {
        return $this->hasMany(Plainte::class, 'plaignant_id');
    }
    public function plaintesRecues()
    {
        return $this->hasMany(Plainte::class, 'mise_en_cause_id');
    }
    public function conversations(): Builder
    {
        return ConversationModel::query()
            ->where('user_1_id', $this->id)
            ->orWhere('user_2_id', $this->id);
    }
}
