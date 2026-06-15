<?php

namespace App\Events;

use App\Models\CandidatureModel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CandidatureCreated implements ShouldBroadcastNow
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
            new PrivateChannel('appel-offre.' . $this->candidature->appeloffer_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'candidature.created';
    }

    public function broadcastWith(): array
    {
        return [
            'candidature' => [
                'id' => $this->candidature->id,
                'appel_offre_id' => $this->candidature->appeloffer_id,
                'artisan_id' => $this->candidature->artisan_id,
                'artisan_user_id' => $this->candidature->artisan?->user_id,
                'artisan_name' => $this->candidature->artisan?->user?->name,
                'description' => $this->candidature->description,
                'devis_propose' => $this->candidature->devis_propose,
                'statut' => $this->candidature->statut,
                'created_at' => optional($this->candidature->created_at)?->toISOString(),
            ],
        ];
    }
}
