<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PostRequest;
use App\Models\ArtisanModel;
use App\Models\AvisModel;
use App\Models\PaymentModel;
use App\Models\PostModel;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

class ArtisanController extends Controller
{
    public function demandecertification(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || ! $user->artisan) {
            return response()->json([
                'success' => false,
                'message' => 'Seul un artisan peut demander une certification.',
            ], 403);
        }

        if (! config('services.fedapay.key')) {
            return response()->json([
                'success' => false,
                'message' => 'La cle secrete Fedapay n est pas configuree.',
            ], 500);
        }

        $validated = $request->validate([
            'diplome' => ['required', 'file', 'mimes:pdf', 'max:5120'],
            'piece_identites' => ['required_without:piece_identite', 'file', 'mimes:pdf', 'max:5120'],
            'piece_identite' => ['required_without:piece_identites', 'file', 'mimes:pdf', 'max:5120'],
            'nom_association' => ['required', 'string', 'max:150'],
            'telephone_association' => ['required', 'string', 'max:30'],
        ]);

        $diplomePath = null;
        $pieceIdentitePath = null;

        try {
            $artisan = $user->artisan;
            $localReference = 'CERT-' . Str::uuid();
            $amount = (int) config('services.fedapay.certification_amount', 1000);

            $diplomePath = $request->file('diplome')->store('certifications/diplomes', 'public');
            $pieceIdentitePath = ($request->file('piece_identites') ?? $request->file('piece_identite'))->store('certifications/pieces-identites', 'public');

            $payment = PaymentModel::create([
                'artisan_id' => $artisan->id,
                'fedapay_transaction_id' => $localReference,
                'local_reference' => $localReference,
                'type_evenement' => 'certification_artisan',
                'montant' => $amount,
                'type' => 'badge',
                'statut' => 'pending',
                'certification_payload' => [
                    'diplome' => $diplomePath,
                    'piece_identites' => $pieceIdentitePath,
                    'nom_association' => $validated['nom_association'],
                    'telephone_association' => $validated['telephone_association'],
                ],
            ]);

            $customer = array_filter([
                'firstname' => $user->name,
                'email' => $user->email,
                'phone_number' => $user->telephone ? [
                    'number' => $user->telephone,
                    'country' => 'bj',
                ] : null,
            ]);

            $transactionResponse = $this->fedapayRequest()->post($this->fedapayBaseUrl() . '/transactions', [
                'description' => 'Demande de certification artisan',
                'amount' => $amount,
                'currency' => ['iso' => 'XOF'],
                'callback_url' => route('fedapay.certification.callback', ['reference' => $localReference]),
                'custom_metadata' => [
                    'payment_id' => $payment->id,
                    'local_reference' => $localReference,
                    'artisan_id' => $artisan->id,
                    'type' => 'certification_artisan',
                ],
                'customer' => $customer,
            ]);

            if ($transactionResponse->failed()) {
                throw new Exception($transactionResponse->body());
            }

            $transaction = $transactionResponse->json();
            $transactionId = $this->extractFedapayTransactionId($transaction);

            if (! $transactionId) {
                throw new Exception('Identifiant de transaction Fedapay introuvable. Reponse Fedapay: ' . $this->shortJson($transaction));
            }

            $tokenResponse = $this->fedapayRequest()->post($this->fedapayBaseUrl() . "/transactions/{$transactionId}/token");

            if ($tokenResponse->failed()) {
                throw new Exception($tokenResponse->body());
            }

            $token = $tokenResponse->json();
            $paymentUrl = $this->extractFedapayPaymentUrl($token);

            if (! $paymentUrl) {
                throw new Exception('Lien de paiement Fedapay introuvable. Reponse Fedapay: ' . $this->shortJson($token));
            }

            $payment->update([
                'fedapay_transaction_id' => (string) $transactionId,
                'payment_url' => $paymentUrl,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Demande recue. Redirigez l artisan vers Fedapay pour effectuer le paiement.',
                'data' => [
                    'payment_id' => $payment->id,
                    'fedapay_transaction_id' => (string) $transactionId,
                    'payment_url' => $paymentUrl,
                ],
            ], 201);
        } catch (\Throwable $e) {
            if ($diplomePath) {
                Storage::disk('public')->delete($diplomePath);
            }
            if ($pieceIdentitePath) {
                Storage::disk('public')->delete($pieceIdentitePath);
            }

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la demande de certification.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function fedapayCertificationCallback(Request $request, string $reference): JsonResponse
    {
        try {
            $payment = PaymentModel::query()
                ->where('local_reference', $reference)
                ->where('type_evenement', 'certification_artisan')
                ->firstOrFail();

            $transactionResponse = $this->fedapayRequest()->get($this->fedapayBaseUrl() . "/transactions/{$payment->fedapay_transaction_id}");

            if ($transactionResponse->failed()) {
                throw new Exception($transactionResponse->body());
            }

            $transaction = $transactionResponse->json();
            $fedapayStatus = data_get($transaction, 'status') ?? data_get($transaction, 'transaction.status');
            $status = $this->mapFedapayStatus($fedapayStatus);

            if ($status !== 'paid') {
                $payment->update(['statut' => $status]);

                return response()->json([
                    'success' => false,
                    'message' => 'Le paiement Fedapay n est pas encore valide.',
                    'data' => [
                        'payment_status' => $status,
                        'fedapay_status' => $fedapayStatus,
                    ],
                ], 202);
            }

            DB::transaction(function () use ($payment): void {
                $payload = $payment->certification_payload ?? [];

                $payment->artisan->update([
                    'diplome' => $payload['diplome'] ?? null,
                    'piece_identites' => $payload['piece_identites'] ?? null,
                    'nom_association' => $payload['nom_association'] ?? null,
                    'telephone_association' => $payload['telephone_association'] ?? null,
                    'is_certifed' => false,
                ]);

                $payment->update([
                    'statut' => 'paid',
                    'paid_at' => now(),
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => 'Paiement valide. Les informations de certification ont ete enregistrees.',
                'data' => [
                    'payment' => $payment->refresh(),
                    'artisan' => $payment->artisan->refresh(),
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la validation du paiement Fedapay.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function createposte(PostRequest $postrequest): JsonResponse
    {
        $user = $postrequest->user();
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur non authentifie.',
            ], 401);
        }
        $authorization = Gate::forUser($user)->inspect('creerposte');
        if (! $authorization->allowed()) {
            return response()->json([
                'success' => false,
                'message' => $authorization->message(),
            ], $authorization->status() ?? 403);
        }
        $mediaPaths = [];
        try {
            $validated = $postrequest->validated();
            $artisan = $user->artisan;
            foreach ($postrequest->file('media_json', []) as $media) {
                $mediaPaths[] = $media->store('posts', 'public');
            }
            $post = PostModel::create([
                'artisan_id' => $artisan->id,
                'description' => $validated['description'] ?? null,
                'media_json' => $mediaPaths ?: null,
                'post_type' => $validated['post_type'],
            ]);
            $post->load(['artisanP.user']);
            $post->loadCount(['likes', 'commentaires']);

            return response()->json([
                'success' => true,
                'message' => 'Post cree avec succes.',
                'post' => $post,
                'media_urls' => collect($mediaPaths)->map(fn ($path) => Storage::url($path))->all(),
            ], 201);
        }
        catch (Exception $e) {
            foreach ($mediaPaths as $path) {
                Storage::disk('public')->delete($path);
            }
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la creation du post.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function feedPosts(Request $request): JsonResponse
    {
        try {
            $currentUser = $this->resolveRequestUser($request);

            $posts = PostModel::query()
                ->with('artisanP.user')
                ->withCount(['commentaires', 'likes'])
                ->when($currentUser, function (Builder $query, $user): void {
                    $query->withCount([
                        'likes as liked_by_current_user' => fn (Builder $likeQuery) => $likeQuery->where('user_id', $user->id),
                    ]);
                })
                ->latest()
                ->paginate(20);

            if ($posts->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucune publication trouvee.',
                ], 404);
            }

            $postsArray = $this->postsPaginatorToArray($posts);

            return response()->json([
                'success' => true,
                'message' => 'Publications recuperees avec succes.',
                'data' => $postsArray,
            ], 200);
        }
        catch (\Throwable $e) {
             return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function artisanPosts(Request $request, ArtisanModel $artisan): JsonResponse
    {
        try {
            $currentUser = $this->resolveRequestUser($request);

            $posts = PostModel::query()
                ->where('artisan_id', $artisan->id)
                ->with([
                    'artisanP.user',
                    'commentaires.user',
                    'likes',
                ])
                ->withCount(['commentaires', 'likes'])
                ->when($currentUser, function (Builder $query, $user): void {
                    $query->withCount([
                        'likes as liked_by_current_user' => fn (Builder $likeQuery) => $likeQuery->where('user_id', $user->id),
                    ]);
                })
                ->orderByDesc('created_at')
                ->paginate(20);

            if ($posts->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucune publication trouvee pour cet artisan.',
                ], 404);
            }

            return $this->buildPostsResponse(
                $posts,
                [
                    'artisan' => $artisan->load('user'),
                ],
                'Publications de l artisan recuperees avec succes.'
            );
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

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

    public function artisanRealisations(Request $request, ArtisanModel $artisan): JsonResponse
    {
        try {
            $posts = PostModel::query()
                ->where('artisan_id', $artisan->id)
                ->whereIn('post_type', ['realisations', 'services', 'service'])
                ->whereNotNull('media_json')
                ->orderByDesc('created_at')
                ->get(['id', 'artisan_id', 'post_type', 'media_json', 'created_at']);

            $realisations = $posts
                ->flatMap(function (PostModel $post) {
                    return collect($post->media_json ?? [])
                        ->filter(fn ($path) => $this->isImagePath($path))
                        ->map(function (string $path) use ($post) {
                            return [
                                'post_id' => $post->id,
                                'post_type' => $post->post_type,
                                'image_url' => Storage::url($path),
                                'created_at' => $post->created_at,
                            ];
                        })
                        ->values();
                })
                ->values();

            return response()->json([
                'success' => true,
                'message' => 'Realisations recuperees avec succes.',
                'data' => [
                    'artisan' => $artisan->load('user'),
                    'realisations' => $realisations,
                    'stats' => [
                        'total_images' => $realisations->count(),
                        'total_posts' => $posts->count(),
                    ],
                ],
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue.',
                'error' => $e->getMessage(),
            ], 500);
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

    private function buildPostsResponse(LengthAwarePaginator $posts, array $context, string $message): JsonResponse
    {
        $postsArray = $this->postsPaginatorToArray($posts);

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                ...$context,
                'posts' => $postsArray,
            ],
        ]);
    }

    private function postsPaginatorToArray(LengthAwarePaginator $posts): array
    {
        $postsArray = $posts->toArray();
        $postsArray['data'] = collect($postsArray['data'] ?? [])
            ->map(function (array $post) {
                $post['media_urls'] = collect($post['media_json'] ?? [])
                    ->map(fn ($path) => Storage::url($path))
                    ->values()
                    ->all();

                $post['liked_by_current_user'] = (bool) ($post['liked_by_current_user'] ?? false);

                return $post;
            })
            ->values()
            ->all();

        return $postsArray;
    }

    private function resolveRequestUser(Request $request)
    {
        if ($request->user()) {
            return $request->user();
        }

        $token = $request->bearerToken();
        if (! $token) {
            return null;
        }

        return PersonalAccessToken::findToken($token)?->tokenable;
    }

    private function isImagePath(string $path): bool
    {
        $extension = strtolower(pathinfo(parse_url($path, PHP_URL_PATH) ?? $path, PATHINFO_EXTENSION));

        return in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
    }

    private function fedapayRequest()
    {
        return Http::withToken(config('services.fedapay.key'))
            ->acceptJson()
            ->asJson();
    }

    private function fedapayBaseUrl(): string
    {
        return config('services.fedapay.environment') === 'sandbox'
            ? 'https://sandbox-api.fedapay.com/v1'
            : 'https://api.fedapay.com/v1';
    }

    private function extractFedapayTransactionId(array $payload): ?string
    {
        $transactionId = $this->firstScalarValue($payload, [
            'id',
            'transaction.id',
            'data.id',
            'data.transaction.id',
            'v1/transaction.id',
            'v1/transaction.transaction.id',
        ]);

        if ($transactionId) {
            return $transactionId;
        }

        foreach ($payload as $key => $value) {
            if (is_array($value) && str_contains((string) $key, 'transaction')) {
                $transactionId = $this->firstScalarValue($value, ['id', 'transaction.id']);

                if ($transactionId) {
                    return $transactionId;
                }
            }
        }

        return null;
    }

    private function extractFedapayPaymentUrl(array $payload): ?string
    {
        return $this->firstScalarValue($payload, [
            'url',
            'token.url',
            'data.url',
            'data.token.url',
            'v1/token.url',
            'v1/token.token.url',
        ]);
    }

    private function firstScalarValue(array $payload, array $paths): ?string
    {
        foreach ($paths as $path) {
            $value = data_get($payload, $path);

            if (is_scalar($value) && $value !== '') {
                return (string) $value;
            }
        }

        return null;
    }

    private function shortJson(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (! is_string($json)) {
            return '{}';
        }

        return Str::limit($json, 1000);
    }

    private function mapFedapayStatus(?string $status): string
    {
        return match ($status) {
            'approved' => 'paid',
            'canceled', 'cancelled' => 'cancelled',
            'declined', 'failed' => 'failed',
            default => 'pending',
        };
    }
}
