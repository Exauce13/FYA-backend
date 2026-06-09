<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\AppelOffresRequest;
use Illuminate\Http\JsonResponse;
use App\Models\AppelOffreModel;
use App\Models\ArtisanModel;
use App\Models\CandidatureModel;
use App\Models\MetierModel;
use App\Services\NotificationService;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AppeloffreController extends Controller
{
    public function createappeloffre(AppelOffresRequest $appelrequest): JsonResponse
    {
        $appelPaths = [];
        try {
            /** @var \App\Models\User|null $user */
            $user = $appelrequest->user();

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifie.',
                ], 401);
            }
            $validated = $appelrequest->validated();
            $metierId = $validated['metier_id'] ?? MetierModel::query()
                ->where('nom', $validated['metier_nom'] ?? null)
                ->value('id');

            if (! $metierId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le métier ciblé est invalide.',
                ], 422);
            }
            $appelFiles = $appelrequest->file('appel_json', $appelrequest->file('media_json', []));

            foreach ($appelFiles as $mediappel) {
                $appelPaths[] = $mediappel->store('appeloffres', 'public');
            }
            [$appelOffre, $notificationsCount] = DB::transaction(function () use ($user, $validated, $appelPaths, $metierId) {
                $appelOffre = AppelOffreModel::create([
                    'user_id' => $user->id,
                    'titre' => $validated['titre'],
                    'description' => $validated['description'],
                    'appel_json' => $appelPaths ?: null,
                    'metier_id' => $metierId,
                    'ville' => $validated['ville'],
                    'budget' => $validated['budget'] ?? null,
                    'status' => 'open',
                ]);

                $artisans = ArtisanModel::query()
                    ->where('metier_id', $metierId)
                    ->pluck('user_id');
                $notifications = $artisans->map(fn ($userId) => [
                    'user_id' => $userId,
                    'type' => 'nouvel_appel_offre',
                    'data_json' => [
                        'appel_offre_id' => $appelOffre->id,
                        'client_id' => $user->id,
                        'titre' => $appelOffre->titre,
                        'metier_id' => $appelOffre->metier_id,
                        'metier_nom' => $validated['metier_nom'] ?? null,
                        'ville' => $appelOffre->ville,
                        'budget' => $appelOffre->budget,
                        'description' => $appelOffre->description,
                    ],
                ])->all();

                if ($notifications) {
                    app(NotificationService::class)->sendMany($notifications);
                }

                return [$appelOffre, count($notifications)];
            });

            return response()->json([
                'success' => true,
                'message' => 'Appel d\'offre crée avec succès.',
                'appel_offre' => $appelOffre->load('metier', 'user'),
                'notifications_envoyees' => $notificationsCount,
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
                'message' => 'Erreur lors de la création de l\'appel d\'offre.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function closeappeloffre($id, Request $request){
        try{
            $appelOffreclose = AppelOffreModel::findOrFail($id);
            if($appelOffreclose->user_id !== $request->user()->id){
                return response()->json([
                    'success' => false,
                    'message' => 'Vous ne pouvez pas cloturer cet appel d offre.',
                ], 403);
            }
            $appelOffreclose->update([
                'status' => 'closed'
            ]);
            return response()->json([
                'success' => true,
                'message' => 'Cette appel d\'offre est clôturé',
                'appelOffreclose' => $appelOffreclose,
            ], 201);
        }
        catch(Exception $e){
            return response()->json([
                'success' => false,
                'message' => 'Appel d\'offre non clôturé',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function mesAppelsOffres(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifie.',
                ], 401);
            }

            $appelsOffres = AppelOffreModel::query()
                ->where('user_id', $user->id)
                ->with(['user', 'metier', 'candidatures.artisan.user'])
                ->withCount('candidatures')
                ->latest()
                ->paginate(20);

            return response()->json([
                'success' => true,
                'message' => 'Mes appels d offres recuperes avec succes.',
                'data' => $appelsOffres,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function feedAppelsOffres(Request $request): JsonResponse
    {
        try
        {
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
                    'message' => 'Seul un artisan peut voir les appels d offres.',
                ], 403);
            }
            $metier = $artisan->metier;
            if (! $metier) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucun metier defini pour cet artisan.',
                    ], 404);
            }
            $appelsOffres = AppelOffreModel::with('user', 'metier')
                ->withCount('candidatures')
                ->where('status', 'open')
                ->where('metier_id', $metier->id)
                ->latest()
                ->paginate(20);
            if ($appelsOffres->isEmpty())
            {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucun appel d\'offre disponible.',
                ], 404);
            }
            return response()->json([
                'success' => true,
                'message' => 'Appels d\'offres récupérés avec succès.',
                'data' => $appelsOffres,
            ], 200);
        }
        catch (\Throwable $e)
        {
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function postulerAppelOffre(Request $request, AppelOffreModel $appelOffre): JsonResponse
    {
        $devisPath = null;

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
                    'message' => 'Seul un artisan peut postuler a un appel d offre.',
                ], 403);
            }
            if ($appelOffre->status !== 'open') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cet appel d offre est deja cloture.',
                ], 403);
            }
            if ((int) $artisan->metier_id !== (int) $appelOffre->metier_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Votre metier ne correspond pas au metier cible de cet appel d offre.',
                ], 403);
            }
            $validated = $request->validate([
                'description' => ['required', 'string', 'min:10', 'max:1000'],
                'devis_propose' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
            ]);
            $candidatureExiste = CandidatureModel::query()->where('appeloffer_id', $appelOffre->id)->where('artisan_id', $artisan->id)->exists();
            if ($candidatureExiste) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous avez deja postule a cet appel d offre.',
                ], 409);
            }

            if ($request->hasFile('devis_propose')) {
                $devisPath = $request->file('devis_propose')->store('devis', 'public');
            }

            $candidature = CandidatureModel::create([
                'appeloffer_id' => $appelOffre->id,
                'artisan_id' => $artisan->id,
                'description' => $validated['description'],
                'devis_propose' => $devisPath,
                'statut' => 'en_attente',
            ]);
            return response()->json([
                'success' => true,
                'message' => 'Candidature envoyee avec succes.',
                'candidature' => $candidature->load('artisan.user', 'appelOffre'),
                'devis_url' => $devisPath ? Storage::url($devisPath) : null,
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
            if ($devisPath) {
                Storage::disk('public')->delete($devisPath);
            }

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l envoi de la candidature.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function accepterCandidature(Request $request, CandidatureModel $candidature): JsonResponse
    {
        try {
            $user = $request->user();
            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifie.',
                ], 401);
            }
            $candidature->load('appelOffre.metier', 'artisan.user');
            if ($candidature->appelOffre->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous ne pouvez pas accepter une candidature sur cet appel d offre.',
                ], 403);
            }

            if ($candidature->appelOffre->status !== 'open') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cet appel d offre est deja cloture.',
                ], 403);
            }

            $notificationsCount = DB::transaction(function () use ($candidature) {
                $autresCandidatures = CandidatureModel::with('artisan')->where('appeloffer_id', $candidature->appeloffer_id)->where('id', '!=', $candidature->id)->get();
                $candidature->update([
                    'statut' => 'accepter',
                ]);

                foreach ($autresCandidatures as $autreCandidature) {
                    $autreCandidature->update([
                        'statut' => 'refuser',
                    ]);
                }

                $candidature->appelOffre->update([
                    'status' => 'closed',
                ]);

                $notifications = [[
                    'user_id' => $candidature->artisan->user_id,
                    'type' => 'candidature_acceptee',
                    'data_json' => [
                        'candidature_id' => $candidature->id,
                        'appel_offre_id' => $candidature->appelOffre->id,
                        'titre' => $candidature->appelOffre->titre,
                        'metier_id' => $candidature->appelOffre->metier_id,
                        'ville' => $candidature->appelOffre->ville,
                        'budget' => $candidature->appelOffre->budget,
                        'description' => $candidature->appelOffre->description,
                    ],
                ]];

                foreach ($autresCandidatures as $autreCandidature) {
                    $notifications[] = [
                        'user_id' => $autreCandidature->artisan->user_id,
                        'type' => 'candidature_refusee',
                        'data_json' => [
                            'candidature_id' => $autreCandidature->id,
                            'appel_offre_id' => $candidature->appelOffre->id,
                            'titre' => $candidature->appelOffre->titre,
                            'metier_id' => $candidature->appelOffre->metier_id,
                            'ville' => $candidature->appelOffre->ville,
                            'budget' => $candidature->appelOffre->budget,
                            'description' => $candidature->appelOffre->description,
                        ],
                    ];
                }

                app(NotificationService::class)->sendMany($notifications);

                return count($notifications);
            });

            return response()->json([
                'success' => true,
                'message' => 'Candidature acceptee avec succes.',
                'candidature' => $candidature->refresh()->load('artisan.user', 'appelOffre.metier'),
                'notifications_envoyees' => $notificationsCount,
            ]);
        }
        catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l acceptation de la candidature.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

}
