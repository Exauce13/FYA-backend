<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ArtisanModel;
use App\Models\AvisModel;
use App\Models\ClientModel;
use App\Models\User;
use App\Http\Requests\StoreAvisRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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
    public function storeAvis(StoreAvisRequest $request, User $user): JsonResponse
    {
        try {
            $auteur = $request->user();
            if (! $auteur) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifie.',
                ], 401);
            }
            if ((int) $auteur->id === (int) $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous ne pouvez pas laisser un avis sur votre propre profil.',
                ], 422);
            }
            $validated = $request->validated();
            $commentaire = trim((string) ($validated['commentaire'] ?? ''));
            $avis = AvisModel::updateOrCreate(
                [
                    'auteur_id' => $auteur->id,
                    'cible_id' => $user->id,
                ],
                [
                    'note' => $validated['note'],
                    'commentaire' => $commentaire !== '' ? $commentaire : null,
                ]
            );
            return response()->json([
                'success' => true,
                'message' => $avis->wasRecentlyCreated
                    ? 'Avis enregistre avec succes.'
                    : 'Avis mis a jour avec succes.',
                'avis' => $avis->load('auteur', 'cible'),
            ], $avis->wasRecentlyCreated ? 201 : 200);
        }
        catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation.',
                'errors' => $e->errors(),
            ], 422);
        }
    }
    private function buildAvisResponse(Builder $query, array $context, string $message): JsonResponse
    {
        $avis = $query->with(['auteur', 'cible'])->latest()->get();
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
}
