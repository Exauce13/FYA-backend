<?php

use App\Models\ConversationModel;
use App\Models\AppelOffreModel;
use App\Services\UserPresenceService;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    app(UserPresenceService::class)->markOnline($user);

    return (int) $user->id === (int) $id;
});
Broadcast::channel('chat.{conversationId}', function ($user, $conversationId) {
    app(UserPresenceService::class)->markOnline($user);

    return ConversationModel::query()
        ->where('id', $conversationId)
        ->where(function ($query) use ($user) {
            $query->where('user_1_id', $user->id)
                ->orWhere('user_2_id', $user->id);
        })
        ->exists();
});

Broadcast::channel('metier.{metierId}', function ($user, $metierId) {
    app(UserPresenceService::class)->markOnline($user);

    return (int) optional($user->artisan)->metier_id === (int) $metierId;
});

Broadcast::channel('appel-offre.{appelOffreId}', function ($user, $appelOffreId) {
    app(UserPresenceService::class)->markOnline($user);

    return AppelOffreModel::query()
        ->where('id', $appelOffreId)
        ->where(function ($query) use ($user) {
            $query->where('user_id', $user->id)
                ->orWhereHas('candidatures.artisan', fn ($artisanQuery) => $artisanQuery->where('user_id', $user->id));
        })
        ->exists();
});

Broadcast::routes(['middleware' => ['auth:sanctum']]);
