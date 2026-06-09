<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\registerRequest;
use App\Http\Requests\AuthenticateRequest;
use App\Http\Requests\PhotoUpdateRequest;
use App\Http\Requests\UpdateInfoRequest;
use App\Http\Requests\UpdatePasswordRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Http\JsonResponse;
use App\Models\AppelOffreModel;
use App\Models\AvisModel;
use App\Models\ArtisanModel;
use App\Models\ClientModel;
use App\Models\MetierModel;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Exception;

class UserController extends Controller
{
    public function register(registerRequest $request)
    {
        try {
            $user = DB::transaction(function () use ($request) {
                $validated = $request->validated();
                $metierId = $validated['metier_id'] ?? MetierModel::query()
                    ->where('nom', $validated['metier_nom'] ?? null)
                    ->value('id');

                if (($validated['statut'] ?? null) === 'artisans' && ! $metierId) {
                    throw new Exception('Le métier choisi est invalide.');
                }

                $user = User::create([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'password' => $validated['password'],
                    'statut' => $validated['statut'],
                    'telephone' => $validated['telephone'],
                    'ville' => $validated['ville'] ?? null,
                    'quartier' => $validated['quartier'] ?? null,
                    'photo' => $validated['photo'] ?? null,
                ]);
                if ($validated['statut'] === 'artisans') {
                    ArtisanModel::create([
                        'user_id' => $user->id,
                        'metier_id' => $metierId,
                        'bio' => $validated['bio']  ?? null,
                        'npi' => $validated['npi'],
                        'annees_experiences' => $validated['annees_experiences'],
                        'nom_association' => $validated['nom_association'] ?? null,
                        'telephone_association' => $validated['telephone_association'] ?? null,
                        'diplome' => $validated['diplome'] ?? null,
                    ]);
                }
                if ($validated['statut'] === 'clients') {
                    ClientModel::create([
                        'user_id' => $user->id,
                    ]);
                }
                return $user;
            });

            $user->sendEmailVerificationNotification();

            return response()->json([
                'success' => true,
                'message' => 'Inscription effectuee avec succes. Veuillez verifier votre adresse email.',
                'user' => $user,
            ], 201);
        }
        catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l inscription.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function verifyEmail(Request $request, int $id, string $hash)
    {
        $user = User::findOrFail($id);

        if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return response()->json([
                'success' => false,
                'message' => 'Lien de verification invalide.',
            ], 403);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        return response()->json([
            'success' => true,
            'message' => 'Adresse email verifiee avec succes.',
        ]);
    }
    public function resendVerificationEmail(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if ($user && ! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return response()->json([
            'success' => true,
            'message' => 'Si cette adresse existe et n est pas encore verifiee, un nouveau lien a ete envoye.',
        ]);
    }
    public function authenticate(AuthenticateRequest $request): JsonResponse
    {
        try {
            $credentials = $request->validated();
            $user = User::where('email', $credentials['email'])->first();

            if (! $user || ! Hash::check($credentials['password'], $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Identifiants incorrects.',
                ], 401);
            }

            if (! $user->hasVerifiedEmail()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Veuillez verifier votre adresse email avant de vous connecter.',
                ], 403);
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Connexion effectuee avec succes.',
                'user' => $user->load(['artisan.metier', 'client']),
                'token' => $token,
                'token_type' => 'Bearer',
            ]);
        }
        catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la connexion.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json([
            'message' => 'Déconnexion réussie'
            ]);
    }
    public function changementphoto(PhotoUpdateRequest $request): JsonResponse
    {
        try {
            /** @var \App\Models\User|null $user */
            $user = $request->user();
            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifie.',
                ], 401);
            }
            if ($user->photo && Storage::disk('public')->exists($user->photo)) {
                Storage::disk('public')->delete($user->photo);
            }
            $path = $request->file('photo')->store('profile-photos', 'public');
            $user->photo = $path;
            $user->save();
            return response()->json([
                'success' => true,
                'message' => 'Photo de profil modifiee avec succes.',
                'photo' => $path,
                'photo_url' => Storage::url($path),
                'user' => $user,
            ]);
        }
        catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la modification de la photo de profil.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function updateinfos(User $user, UpdateInfoRequest $request){
        try{
            $validated = $request->validated();

            $user->fill([
                'telephone' => $validated['telephone'] ?? $user->telephone,
                'ville' => $validated['ville'] ?? $user->ville,
                'quartier' => $validated['quartier'] ?? $user->quartier,
            ]);
            $user->save();

            if ($user->artisan) {
                $artisanData = [
                    'bio' => array_key_exists('bio', $validated) ? $validated['bio'] : $user->artisan->bio,
                    'nom_atelier' => array_key_exists('nom_atelier', $validated) ? $validated['nom_atelier'] : $user->artisan->nom_atelier,
                    'annees_experiences' => array_key_exists('annees_experiences', $validated) ? $validated['annees_experiences'] : $user->artisan->annees_experiences,
                ];

                if ($request->hasFile('diplome')) {
                    $artisanData['diplome'] = $request->file('diplome')->store('diplomes', 'public');
                } elseif (array_key_exists('diplome', $validated)) {
                    $artisanData['diplome'] = $validated['diplome'];
                }

                $user->artisan->update($artisanData);
            }

            return response()->json([
                'success' => true,
                'message' => 'Modification effectuee avec succes.',
                'user' => $user,
            ], 200);
        }
        catch(Exception $e){
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la modification des informations',
                'error' => $e->getMessage(),
            ],500);
        }
    }
    public function updatemdp(UpdatePasswordRequest $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifie.',
                ], 401);
            }

            $validated = $request->validated();

            if (! Hash::check($validated['old_password'], $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'L ancien mot de passe est incorrect.',
                ], 422);
            }

            if (
                array_key_exists('new_password_confirmation', $validated)
                && $validated['new_password_confirmation'] !== null
                && $validated['new_password_confirmation'] !== $validated['new_password']
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'La confirmation du nouveau mot de passe ne correspond pas.',
                ], 422);
            }

            $user->password = $validated['new_password'];
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Mot de passe modifie avec succes.',
            ], 200);
        }
        catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la modification du mot de passe.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function rechercheArtisan(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'ville' => ['nullable', 'string', 'max:50'],
                'quartier' => ['nullable', 'string', 'max:50'],
                'metier_id' => ['required', 'integer', 'exists:metiers,id'],
                'certifie' => ['sometimes', 'boolean'],
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de validation.',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $validated = $validator->validated();

            $artisans = ArtisanModel::with('user', 'metier')
                ->where('metier_id', (int) $validated['metier_id'])
                ->when(! empty($validated['ville'] ?? null), function ($query) use ($validated) {
                    $query->whereHas('user', function ($userQuery) use ($validated) {
                        $userQuery->where('ville', 'like', '%' . $validated['ville'] . '%');
                    });
                })
                ->when(! empty($validated['quartier'] ?? null), function ($query) use ($validated) {
                    $query->whereHas('user', function ($userQuery) use ($validated) {
                        $userQuery->where('quartier', 'like', '%' . $validated['quartier'] . '%');
                    });
                })
                ->when($request->boolean('certifie'), function ($query) {
                    $query->where('is_certifed', true);
                })
                ->get()
                ->map(function (ArtisanModel $artisan) {
                    return [
                        'id' => $artisan->id,
                        'name' => $artisan->user?->name,
                        'ville' => $artisan->user?->ville,
                        'quartier' => $artisan->user?->quartier,
                        'telephone' => $artisan->user?->telephone,
                        'photo' => $artisan->user?->photo,
                        'metier_id' => $artisan->metier_id,
                        'metier' => $artisan->metier ? [
                            'id' => $artisan->metier->id,
                            'nom' => $artisan->metier->nom,
                        ] : null,
                        'bio' => $artisan->bio,
                        'npi' => $artisan->npi,
                        'annees_experiences' => $artisan->annees_experiences,
                        'nom_association' => $artisan->nom_association,
                        'telephone_association' => $artisan->telephone_association,
                        'diplome' => $artisan->diplome,
                        'is_certifed' => $artisan->is_certifed,
                        'is_boost' => $artisan->is_boost,
                    ];
                });

            if ($request->boolean('certifie') && $artisans->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucun artisan certifie ne correspond a ces criteres.',
                    'artisans' => [],
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Recherche effectuee avec succes.',
                'artisans' => $artisans,
            ]);
        }
        catch(Exception $e){
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la recherche des artisans.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

}
