<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePlainteRequest;
use App\Models\Plainte;
use Illuminate\Http\JsonResponse;
use Throwable;

class PlainteController extends Controller
{
    public function plaintes(StorePlainteRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $validated = $request->validated();

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifie.',
                    'data' => null,
                ], 401);
            }
            $plainte = Plainte::create([
                'plaignant_id' => $user->id,
                'mise_en_cause_id' => (int) $validated['mise_en_cause_id'],
                'motif' => $validated['motif'],
                'description' => $validated['description'] ?? null,
                'statut_plainte' => Plainte::STATUT_EN_ATTENTE,
            ])->load(['plaignant', 'miseEnCause']);
            return response()->json([
                'success' => true,
                'message' => 'Plainte créée avec succès.',
                'data' => $plainte,
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la création de la plainte.',
                'data' => null,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
