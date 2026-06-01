<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NotificationModel;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifie.',
                ], 401);
            }
            $notifications = NotificationModel::query()->where('user_id', $user->id)->latest()->get();
            return response()->json([
                'success' => true,
                'message' => 'Notifications recuperees avec succes.',
                'notifications' => $notifications,
                'non_lues' => $notifications->whereNull('read_at')->count(),
            ]);
        }
        catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la recuperation des notifications.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function lire(Request $request, NotificationModel $notification): JsonResponse
    {
        try {
            $user = $request->user();
            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifie.',
                ], 401);
            }
            if ($notification->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous ne pouvez pas lire cette notification.',
                ], 403);
            }
            if (! $notification->read_at) {
                $notification->update([
                    'read_at' => now(),
                ]);
            }
            return response()->json([
                'success' => true,
                'message' => 'Notification marquee comme lue.',
                'notification' => $notification,
            ]);
        }
        catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la lecture de la notification.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function toutLire(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifie.',
                ], 401);
            }
            $notificationsCount = NotificationModel::query()->where('user_id', $user->id)->whereNull('read_at')->update(['read_at' => now()]);
            return response()->json([
                'success' => true,
                'message' => 'Toutes les notifications ont ete marquees comme lues.',
                'notifications_lues' => $notificationsCount,
            ]);
        }
        catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la lecture des notifications.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
