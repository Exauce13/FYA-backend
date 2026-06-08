<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Models\LikeModel;
use App\Models\PostModel;
use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;

class LikesController extends Controller
{
    public function like(Request $request, int $postid): JsonResponse
    {
        try{
            $user = $request->user();
            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifie.',
                ], 401);
            }
            $post = PostModel::findOrFail($postid);
            $like = LikeModel::where('user_id', $user->id)->where('post_id', $postid)->first();
            if($like)
            {
                $like->delete();
                $post->loadCount('likes');

                return response()->json([
                    'success' => true,
                    'message' => 'Like retiré avec succès.',
                    'liked' => false,
                    'likes_count' => $post->likes_count,
                ]);
            }
            else{
                $like=LikeModel::create([
                    'user_id' => $user->id,
                    'post_id' => $postid,
                ]);
            }
            $post->loadCount('likes');

            return response()->json([
                'success' => true,
                'message' => 'Post aimé avec succès.',
                'liked' => true,
                'likes_count' => $post->likes_count,
                'like' => $like,
            ]);
        }
        catch(ModelNotFoundException $e){
            return response()->json([
                'success' => false,
                'message' => 'Post introuvable.',
            ], 404);
        }
        catch(Exception $e){
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du like.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
