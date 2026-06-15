<?php

namespace App\Services;

use App\Events\RealtimeNotificationSent;
use App\Mail\UserNotificationMail;
use App\Models\NotificationModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    public function __construct(private readonly UserPresenceService $presence)
    {
    }

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

            DB::afterCommit(function () use ($notification): void {
                try {
                    $freshNotification = $notification->fresh('user');

                    if (! $freshNotification) {
                        return;
                    }

                    if ($this->presence->isOnline((int) $freshNotification->user_id)) {
                        event(new RealtimeNotificationSent($freshNotification));

                        return;
                    }

                    if ($freshNotification->user?->email) {
                        Mail::to($freshNotification->user->email)->send(new UserNotificationMail($freshNotification));
                    }
                } catch (\Throwable $e) {
                    report($e);
                }
            });
        }

        return count($createdNotifications);
    }
}
