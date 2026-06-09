<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PostModel;
use App\Models\CommentaireModel;
use Exception;
use Illuminate\Http\JsonResponse;

class CommentairesController extends Controller
{
    public function affichercommentaire(PostModel $post): JsonResponse
    {
        try {
            $commentaires = $post->commentaires()->with('user')->latest()->get();
            $post->loadCount('commentaires');

            return response()->json([
                'success' => true,
                'message' => 'Commentaires recuperes avec succes.',
                'commentaires' => $commentaires,
                'commentaires_count' => $post->commentaires_count,
            ]);
        }
        catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la recuperation des commentaires.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    # la méthode qui gère commment poster un commentaire
    public function postercommentaire(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur non authentifie.',
            ], 401);
        }

        $validated = $request->validate([
            'post_id' => ['required', 'integer', 'exists:posts,id'],
            'comments' => ['nullable', 'string', 'max:255', 'required_without:content'],
            'content' => ['nullable', 'string', 'max:255', 'required_without:comments'],
        ]);
        try {
            $post = PostModel::findOrFail($validated['post_id']);
            $commentaire = CommentaireModel::create([
                'comments' => $validated['comments'] ?? $validated['content'],
                'user_id' => $user->id,
                'post_id' => $validated['post_id'],
            ]);
            $post->loadCount('commentaires');

            return response()->json([
                'success' => true,
                'message' => 'Commentaire poste avec succes.',
                'commentaire' => $commentaire->load('user'),
                'commentaires_count' => $post->commentaires_count,
            ], 201);
        }
        catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la creation du commentaire.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
