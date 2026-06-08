<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostModel extends Model
{
    protected $table = "posts";
    protected $fillable = ['artisan_id', 'description', 'media_json', 'post_type'];

    protected function casts(): array
    {
        return [
            'media_json' => 'array',
        ];
    }
    public function artisanP(){
        return $this->belongsTo(ArtisanModel::class, 'artisan_id');
    }
    public function commentaires(){
        return $this->hasMany(CommentaireModel::class, 'post_id');
    }

    public function likes()
    {
        return $this->hasMany(LikeModel::class, 'post_id');
    }
}
