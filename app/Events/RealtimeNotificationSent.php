<?php

namespace App\Events;

use App\Models\NotificationModel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RealtimeNotificationSent implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public NotificationModel $notification)
    {
        $this->notification->loadMissing('user');
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('App.Models.User.' . $this->notification->user_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    public function broadcastWith(): array
    {
        return [
            'notification' => [
                'id' => $this->notification->id,
                'user_id' => $this->notification->user_id,
                'type' => $this->notification->type,
                'data_json' => $this->notification->data_json,
                'read_at' => optional($this->notification->read_at)?->toISOString(),
                'created_at' => optional($this->notification->created_at)?->toISOString(),
                'updated_at' => optional($this->notification->updated_at)?->toISOString(),
            ],
        ];
    }
}
