<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NotificationModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminNotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = NotificationModel::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return response()->json($notifications);
    }

    public function markAsRead(NotificationModel $notification): JsonResponse
    {
        $notification->update(['read_at' => now()]);

        return response()->json([
            'message' => 'Notification marquee comme lue.',
            'data' => $notification->refresh(),
        ]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $count = NotificationModel::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'message' => 'Toutes les notifications ont ete marquees comme lues.',
            'notifications_lues' => $count,
        ]);
    }
}
