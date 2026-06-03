<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConversationModel extends Model
{
    protected $table = "conversations";
    protected $fillable = ['title', 'type', 'user_1_id', 'user_2_id'];

    public function messages()
    {
        return $this->hasMany(MessageModel::class);
    }

    public function userOne()
    {
        return $this->belongsTo(User::class, 'user_1_id');
    }

    public function userTwo()
    {
        return $this->belongsTo(User::class, 'user_2_id');
    }

    public function participants()
    {
        return collect([$this->userOne, $this->userTwo])->filter()->values();
    }

    public function containsUser(int $userId): bool
    {
        return (int) $this->user_1_id === $userId || (int) $this->user_2_id === $userId;
    }

    public function otherParticipantFor(int $userId): ?User
    {
        if ((int) $this->user_1_id === $userId) {
            return $this->userTwo;
        }

        if ((int) $this->user_2_id === $userId) {
            return $this->userOne;
        }

        return null;
    }
}
