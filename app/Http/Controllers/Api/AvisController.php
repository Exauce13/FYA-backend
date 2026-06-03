<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ArtisanModel;
use App\Models\AvisModel;
use App\Models\ClientModel;
use App\Models\ServiceModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AvisController extends Controller
{
    public function artisanAvis(Request $request, ArtisanModel $artisan): JsonResponse
    {
        return $this->buildAvisResponse(
            AvisModel::query()->where('cible_id', $artisan->user_id),
            [
                'artisan' => $artisan->load('user'),
            ],
            'Avis de l artisan recuperes avec succes.'
        );
    }
    public function clientAvis(Request $request, ClientModel $client): JsonResponse
    {
        return $this->buildAvisResponse(
            AvisModel::query()->where('cible_id', $client->user_id),
            [
                'client' => $client->load('user'),
            ],
            'Avis du client recuperes avec succes.'
        );
    }
    public function serviceAvis(Request $request, ServiceModel $service): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur non authentifie.',
            ], 401);
        }
        $service->loadMissing('client.user', 'artisan.user');
        if (! $this->userPeutAccederAuService($user, $service)) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez pas consulter les avis de ce service.',
            ], 403);
        }
        return $this->buildAvisResponse(
            AvisModel::query()->where('service_id', $service->id),
            [
                'service' => $service->load('client.user', 'artisan.user'),
            ],
            'Avis du service recuperes avec succes.'
        );
    }
    private function buildAvisResponse(Builder $query, array $context, string $message): JsonResponse
    {
        $avis = $query->with(['auteur', 'cible', 'service.client.user', 'service.artisan.user'])->latest()->get();
        $stats = [
            'total_avis' => $avis->count(),
            'moyenne_note' => $avis->avg('note') !== null ? round((float) $avis->avg('note'), 2) : null,
        ];
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                ...$context,
                'avis' => $avis,
                'stats' => $stats,
            ],
        ]);
    }
    private function userPeutAccederAuService($user, ServiceModel $service): bool
    {
        if ($user->artisan && $service->artisan_id === $user->artisan->id) {
            return true;
        }
        return $user->client && $service->client_id === $user->client->id;
    }
}
