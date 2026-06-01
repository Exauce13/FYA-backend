<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\registerRequest;
use App\Http\Requests\AuthenticateRequest;
use App\Http\Requests\PhotoUpdateRequest;
use App\Http\Requests\UpdateInfoRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Http\JsonResponse;
use App\Models\AppelOffreModel;
use App\Models\ArtisanModel;
use App\Models\ClientModel;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Exception;

class UserController extends Controller
{
    public function register(registerRequest $request)
    {
        try {
            $user = DB::transaction(function () use ($request) {
                $validated = $request->validated();
                $user = User::create([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'password' => $validated['password'],
                    'statut' => $validated['statut'],
                    'ville' => $validated['ville'],
                    'quartier' => $validated['quartier'],
                    'telephone' => $validated['telephone'],
                    'photo' => $validated['photo'] ?? null,
                ]);
                if ($validated['statut'] === 'artisans') {
                    ArtisanModel::create([
                        'user_id' => $user->id,
                        'metiers' => $validated['metiers'],
                        'bio' => $validated['bio'],
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
                'user' => $user,
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
    public function changementprofile(PhotoUpdateRequest $request): JsonResponse
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
            User::update([
                'password' => $validated['password'],
                'telephone' => $validated['telephone'],
                'ville' => $validated['ville'],
                'quartier' => $validated['quartier'],
            ]);
            if($user->artisan){
                ArtisanModel::update([
                    'annees_experiences' => $validated['annees_experiences'],
                    'diplome' => $validated['diplome'],
                ]);
            }
            return response()->json([
                'success' => true,
                'message' => 'Modification effectuee avec succes. Veuillez verifier votre adresse email.',
                'user' => $user,
            ],201);
        }
        catch(Exception $e){
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la modification des informations',
                'error' => $e->getMessage(),
            ],500);
        }
    }
    public function rechercheArtisan(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'ville' => ['required', 'string', 'max:50'],
                'quartier' => ['required', 'string', 'max:50'],
                'metiers' => ['required', 'string', 'max:255'],
            ]);
            $artisans = ArtisanModel::with('user')->where('metiers', 'like', '%' . $validated['metiers'] . '%')->whereHas('user', function ($query) use ($validated) {
                    $query->where('ville', 'like', '%' . $validated['ville'] . '%')->where('quartier', 'like', '%' . $validated['quartier'] . '%');
                })->get();
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
