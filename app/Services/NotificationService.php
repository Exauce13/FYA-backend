<?php

namespace App\Services;

use App\Events\RealtimeNotificationSent;
use App\Models\NotificationModel;
use Illuminate\Support\Facades\DB;

class NotificationService
{
    /**
     * Persist notifications and broadcast them after the surrounding transaction commits.
     *
     * @param  array<int, array<string, mixed>>  $notifications
     */
    public function sendMany(array $notifications): int
    {
        $createdNotifications = [];

        foreach ($notifications as $payload) {
            $notification = NotificationModel::create([
                'user_id' => $payload['user_id'],
                'type' => $payload['type'],
                'data_json' => $payload['data_json'] ?? null,
                'read_at' => $payload['read_at'] ?? null,
            ]);

            $createdNotifications[] = $notification;

            DB::afterCommit(static function () use ($notification): void {
                event(new RealtimeNotificationSent($notification->fresh()));
            });
        }

        return count($createdNotifications);
    }
}
