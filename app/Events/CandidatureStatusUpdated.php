<?php

namespace App\Events;

use App\Models\CandidatureModel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CandidatureStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public CandidatureModel $candidature)
    {
        $this->candidature->loadMissing('artisan.user', 'appelOffre');
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('App.Models.User.' . $this->candidature->artisan->user_id),
            new PrivateChannel('appel-offre.' . $this->candidature->appeloffer_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'candidature.status.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'candidature' => [
                'id' => $this->candidature->id,
                'appel_offre_id' => $this->candidature->appeloffer_id,
                'artisan_id' => $this->candidature->artisan_id,
                'artisan_user_id' => $this->candidature->artisan->user_id,
                'statut' => $this->candidature->statut,
                'titre' => $this->candidature->appelOffre?->titre,
                'updated_at' => optional($this->candidature->updated_at)?->toISOString(),
            ],
        ];
    }
}
