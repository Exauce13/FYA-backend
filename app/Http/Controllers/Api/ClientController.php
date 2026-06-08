<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppelOffreModel;
use App\Models\AvisModel;
use App\Models\ClientModel;
use App\Models\ServiceModel;
use Illuminate\Http\JsonResponse;

class ClientController extends Controller
{
    public function appelsOffres(ClientModel $client): JsonResponse
    {
        try {
            $appelsOffres = AppelOffreModel::query()->where('user_id', $client->user_id)->with(['user', 'metier', 'candidatures.artisan.user'])->latest()->paginate(20);

            return response()->json([
                'success' => true,
                'message' => 'Appels d offres du client recuperes avec succes.',
                'data' => [
                    'client' => $client->load('user'),
                    'appels_offres' => $appelsOffres,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la recuperation des appels d offres du client.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function services(ClientModel $client): JsonResponse
    {
        try {
            $services = ServiceModel::query()->where('client_id', $client->id)->with(['client.user', 'artisan.user', 'message', 'appelOffre.metier'])->latest()->paginate(20);
            return response()->json([
                'success' => true,
                'message' => 'Services du client recuperes avec succes.',
                'data' => [
                    'client' => $client->load('user'),
                    'services' => $services,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la recuperation des services du client.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function avis(ClientModel $client): JsonResponse
    {
        try {
            $avis = AvisModel::query()->where('cible_id', $client->user_id)->with(['auteur', 'cible'])->latest()->get();

            return response()->json([
                'success' => true,
                'message' => 'Avis du client recuperes avec succes.',
                'data' => [
                    'client' => $client->load('user'),
                    'avis' => $avis,
                    'stats' => [
                        'total_avis' => $avis->count(),
                        'moyenne_note' => $avis->avg('note') !== null ? round((float) $avis->avg('note'), 2) : null,
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la recuperation des avis du client.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
