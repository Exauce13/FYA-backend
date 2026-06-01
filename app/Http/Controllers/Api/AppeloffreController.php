<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\NotificationModel;
use App\Http\Requests\AppelOffresRequest;
use Illuminate\Http\JsonResponse;
use App\Models\AppelOffreModel;
use App\Models\ArtisanModel;
use App\Models\CandidatureModel;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
            foreach ($appelrequest->file('appel_json', []) as $mediappel) {
                $appelPaths[] = $mediappel->store('appeloffres', 'public');
            }
            [$appelOffre, $notificationsCount] = DB::transaction(function () use ($user, $validated, $appelPaths) {
                $appelOffre = AppelOffreModel::create([
                    'user_id' => $user->id,
                    'description' => $validated['description'],
                    'appel_json' => $appelPaths ?: null,
                    'metiers_cibles' => $validated['metiers_cibles'],
                    'status' => 'open',
                ]);

                $artisans = ArtisanModel::query()->where('metiers', 'like', '%' . $validated['metiers_cibles'] . '%')->pluck('user_id');
                $notifications = $artisans->map(fn ($userId) => [
                    'user_id' => $userId,
                    'type' => 'nouvel_appel_offre',
                    'data_json' => json_encode([
                        'appel_offre_id' => $appelOffre->id,
                        'client_id' => $user->id,
                        'metiers_cibles' => $appelOffre->metiers_cibles,
                        'description' => $appelOffre->description,
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->all();

                if ($notifications) {
                    NotificationModel::insert($notifications);
                }

                return [$appelOffre, count($notifications)];
            });

            return response()->json([
                'success' => true,
                'message' => 'Appel d\'offre crée avec succès.',
                'appel_offre' => $appelOffre,
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
            $metiers = collect(explode(',', $artisan->metiers))->map(fn ($metier) => trim($metier))->filter();
            if ($metiers->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucun metier defini pour cet artisan.',
                ], 404);
            }
            $appelsOffres = AppelOffreModel::with('user')->where('status', 'open')
                ->where(function ($query) use ($metiers) {
                    foreach ($metiers as $metier) {
                        $query->orWhere('metiers_cibles', 'like', '%' . $metier . '%');
                    }
                })->latest()->paginate(20);
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
            if (! $this->artisanPeutPostuler($artisan->metiers, $appelOffre->metiers_cibles)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Votre metier ne correspond pas au metier cible de cet appel d offre.',
                ], 403);
            }
            $validated = $request->validate([
                'description' => ['required', 'string', 'min:10', 'max:1000'],
                'devis_propose' => ['nullable', 'string', 'max:255'],
            ]);
            $candidatureExiste = CandidatureModel::query()->where('appeloffer_id', $appelOffre->id)->where('artisan_id', $artisan->id)->exists();
            if ($candidatureExiste) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous avez deja postule a cet appel d offre.',
                ], 409);
            }
            $candidature = CandidatureModel::create([
                'appeloffer_id' => $appelOffre->id,
                'artisan_id' => $artisan->id,
                'description' => $validated['description'],
                'devis_propose' => $validated['devis_propose'] ?? null,
                'statut' => 'en_attente',
            ]);
            return response()->json([
                'success' => true,
                'message' => 'Candidature envoyee avec succes.',
                'candidature' => $candidature->load('artisan.user', 'appelOffre'),
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
            $candidature->load('appelOffre', 'artisan.user');
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
                    'data_json' => json_encode([
                        'candidature_id' => $candidature->id,
                        'appel_offre_id' => $candidature->appelOffre->id,
                        'metiers_cibles' => $candidature->appelOffre->metiers_cibles,
                        'description' => $candidature->appelOffre->description,
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]];

                foreach ($autresCandidatures as $autreCandidature) {
                    $notifications[] = [
                        'user_id' => $autreCandidature->artisan->user_id,
                        'type' => 'candidature_refusee',
                        'data_json' => json_encode([
                            'candidature_id' => $autreCandidature->id,
                            'appel_offre_id' => $candidature->appelOffre->id,
                            'metiers_cibles' => $candidature->appelOffre->metiers_cibles,
                            'description' => $candidature->appelOffre->description,
                        ]),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                NotificationModel::insert($notifications);

                return count($notifications);
            });

            return response()->json([
                'success' => true,
                'message' => 'Candidature acceptee avec succes.',
                'candidature' => $candidature->refresh()->load('artisan.user', 'appelOffre'),
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

    private function artisanPeutPostuler(?string $metiersArtisan, string $metierCible): bool
    {
        $metiers = collect(explode(',', (string) $metiersArtisan))->map(fn ($metier) => Str::lower(trim($metier)))->filter();

        $metierCible = Str::lower(trim($metierCible));

        return $metiers->contains(fn ($metier) => $metier === $metierCible);
    }
}
