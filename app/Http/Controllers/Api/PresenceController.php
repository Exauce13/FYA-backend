<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UserPresenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PresenceController extends Controller
{
    public function online(Request $request, UserPresenceService $presence): JsonResponse
    {
        $presence->markOnline($request->user());

        return response()->json([
            'success' => true,
            'message' => 'Presence mise a jour.',
        ]);
    }

    public function offline(Request $request, UserPresenceService $presence): JsonResponse
    {
        $presence->markOffline($request->user());

        return response()->json([
            'success' => true,
            'message' => 'Utilisateur marque hors ligne.',
        ]);
    }
}
