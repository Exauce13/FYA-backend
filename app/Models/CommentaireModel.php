<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommentaireModel extends Model
{
    protected $table = "commentaires";
    protected $fillable = ['post_id', 'user_id', 'comments'];

    public function post(){
        return $this->belongsTo(PostModel::class, 'post_id');
    }
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
