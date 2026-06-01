<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PostRequest;
use App\Models\PostModel;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class ArtisanController extends Controller
{
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
    public function feedPosts(): JsonResponse
    {
        try {
            $posts = PostModel::with('artisanP.user')->latest()->paginate(20);

            if ($posts->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucune publication trouvee.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Publications recuperees avec succes.',
                'data' => $posts,
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
}
