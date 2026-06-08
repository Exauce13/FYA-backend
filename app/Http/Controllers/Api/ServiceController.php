<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClientModel;
use App\Models\MessageModel;
use App\Models\ServiceModel;
use App\Models\User;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ServiceController extends Controller
{
    public function creerService(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifie.',
                ], 401);
            }
            $artisan = $user->artisan;
            if (! $artisan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Seul un artisan peut creer un service.',
                ], 403);
            }
            $validated = $request->validate([
                'client_id' => ['required', 'integer', 'exists:clients,id'],
                'message_id' => ['required', 'integer', 'exists:messages,id'],
                'appeloffer_id' => ['nullable', 'integer', 'exists:appels_offres,id'],
                'titre' => ['required', 'string', 'max:255'],
                'description' => ['required', 'string', 'max:1000'],
                'montant' => ['required', 'numeric', 'min:0'],
                'duree_service' => ['required', 'string', 'max:255'],
                'devis' => ['nullable', 'string', 'max:255'],
            ]);
            $client = ClientModel::with('user')->findOrFail($validated['client_id']);
            $message = MessageModel::findOrFail($validated['message_id']);
            $participants = [
                $message->expediteur_id,
                $message->destinataire_id,
            ];
            if (! in_array($user->id, $participants, true) || ! in_array($client->user_id, $participants, true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ce message ne correspond pas a une discussion entre cet artisan et ce client.',
                ], 403);
            }
            if (isset($validated['appeloffer_id']) && $message->appel_offre_id && (int) $validated['appeloffer_id'] !== (int) $message->appel_offre_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'L appel d offre indique ne correspond pas a cette discussion.',
                ], 422);
            }
            $appelOffreId = $message->appel_offre_id ?? ($validated['appeloffer_id'] ?? null);
            $service = ServiceModel::create([
                'client_id' => $client->id,
                'artisan_id' => $artisan->id,
                'message_id' => $message->id,
                'appeloffer_id' => $appelOffreId,
                'titre' => $validated['titre'],
                'description' => $validated['description'],
                'montant' => $validated['montant'],
                'duree_service' => $validated['duree_service'],
                'statut' => 'en_attente',
                'devis' => $validated['devis'] ?? null,
            ]);
            return response()->json([
                'success' => true,
                'message' => 'Service cree avec succes.',
                'service' => $service->load('client.user', 'artisan.user', 'message', 'appelOffre'),
            ], 201);
        }
        catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation.',
                'errors' => $e->errors(),
            ], 422);
        }
        catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la creation du service.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function voirService(Request $request, ServiceModel $service): JsonResponse
    {
        try {
            $user = $request->user();
            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifie.',
                ], 401);
            }
            if (! $this->userPeutAccederAuService($user, $service)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous ne pouvez pas consulter ce service.',
                ], 403);
            }
            if ($user->client && $service->client_id === $user->client->id && ! $service->client_lu_at) {
                $service->update([
                    'client_lu_at' => now(),
                ]);
            }
            return response()->json([
                'success' => true,
                'message' => 'Service recupere avec succes.',
                'service' => $service->refresh()->load('client.user', 'artisan.user', 'message', 'appelOffre'),
            ]);
        }
        catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la recuperation du service.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function validerService(Request $request, ServiceModel $service): JsonResponse
    {
        try {
            $user = $request->user();
            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifie.',
                ], 401);
            }
            if (! $user->client || $service->client_id !== $user->client->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Seul le client concerne peut valider ce service.',
                ], 403);
            }
            if ($service->statut !== 'en_attente') {
                return response()->json([
                    'success' => false,
                    'message' => 'Ce service ne peut plus etre valide.',
                ], 409);
            }
            $service->update([
                'client_lu_at' => $service->client_lu_at ?? now(),
                'client_valide_at' => now(),
                'statut' => 'en_cours',
            ]);
            return response()->json([
                'success' => true,
                'message' => 'Service valide avec succes.',
                'service' => $service->refresh()->load('client.user', 'artisan.user', 'message', 'appelOffre'),
            ]);
        }
        catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la validation du service.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function terminerService(Request $request, ServiceModel $service): JsonResponse
    {
        try {
            $user = $request->user();
            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifie.',
                ], 401);
            }
            if (! $this->userPeutAccederAuService($user, $service)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous ne pouvez pas terminer ce service.',
                ], 403);
            }
            if ($service->statut === 'en_attente') {
                return response()->json([
                    'success' => false,
                    'message' => 'Le client doit d abord valider ce service.',
                ], 409);
            }
            if ($service->statut === 'terminer') {
                return response()->json([
                    'success' => true,
                    'message' => 'Ce service est deja termine.',
                    'service' => $service->load('client.user', 'artisan.user', 'message', 'appelOffre'),
                ]);
            }
            $updates = [];
            if ($user->artisan && $service->artisan_id === $user->artisan->id && ! $service->artisan_termine_at) {
                $updates['artisan_termine_at'] = now();
            }
            if ($user->client && $service->client_id === $user->client->id && ! $service->client_termine_at) {
                $updates['client_termine_at'] = now();
            }
            if (! $updates) {
                return response()->json([
                    'success' => true,
                    'message' => 'Votre confirmation de fin avait deja ete enregistree.',
                    'service' => $service->load('client.user', 'artisan.user', 'message', 'appelOffre'),
                ]);
            }
            $service->fill($updates);
            if ($service->artisan_termine_at && $service->client_termine_at) {
                $service->statut = 'terminer';
            }
            $service->save();
            return response()->json([
                'success' => true,
                'message' => $service->statut === 'terminer'
                    ? 'Service termine avec succes.'
                    : 'Confirmation de fin enregistree. En attente de l\'autre partie.',
                'service' => $service->refresh()->load('client.user', 'artisan.user', 'message', 'appelOffre'),
            ]);
        }
        catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la finalisation du service.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    private function userPeutAccederAuService(User $user, ServiceModel $service): bool
    {
        if ($user->artisan && $service->artisan_id === $user->artisan->id) {
            return true;
        }
        return $user->client && $service->client_id === $user->client->id;
    }
}
