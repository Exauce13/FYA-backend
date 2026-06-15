<?php

namespace App\Events;

use App\Models\AppelOffreModel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AppelOffreCreated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public AppelOffreModel $appelOffre)
    {
        $this->appelOffre->loadMissing('user', 'metier');
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('metier.' . $this->appelOffre->metier_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'appel-offre.created';
    }

    public function broadcastWith(): array
    {
        return [
            'appel_offre' => [
                'id' => $this->appelOffre->id,
                'client_id' => $this->appelOffre->user_id,
                'client_name' => $this->appelOffre->user?->name,
                'titre' => $this->appelOffre->titre,
                'description' => $this->appelOffre->description,
                'metier_id' => $this->appelOffre->metier_id,
                'metier_nom' => $this->appelOffre->metier?->nom,
                'ville' => $this->appelOffre->ville,
                'budget' => $this->appelOffre->budget,
                'status' => $this->appelOffre->status,
                'created_at' => optional($this->appelOffre->created_at)?->toISOString(),
            ],
        ];
    }
}
