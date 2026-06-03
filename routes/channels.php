<?php

use App\Models\ConversationModel;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
Broadcast::channel('chat.{conversationId}', function ($user, $conversationId) {
    return ConversationModel::query()
        ->where('id', $conversationId)
        ->where(function ($query) use ($user) {
            $query->where('user_1_id', $user->id)
                ->orWhere('user_2_id', $user->id);
        })
        ->exists();
});
// Exemple pour Laravel 11 / 2026 dans bootstrap/app.php ou config
Broadcast::routes(['middleware' => ['auth:sanctum']]);
