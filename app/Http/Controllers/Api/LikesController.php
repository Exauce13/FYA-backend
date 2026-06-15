<?php

namespace App\Http\Controllers\Api;

use App\Events\PostLikeUpdated;
use Illuminate\Http\Request;
use App\Models\LikeModel;
use App\Models\PostModel;
use App\Http\Controllers\Controller;
use App\Services\NotificationService;
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
            $post = PostModel::with('artisanP.user')->findOrFail($postid);
            $like = LikeModel::where('user_id', $user->id)->where('post_id', $postid)->first();
            if($like)
            {
                $like->delete();
                $post->loadCount('likes');
                $this->broadcastLikeUpdated($post->id, $user->id, false, $post->likes_count);

                return response()->json([
                    'success' => true,
                    'message' => 'Like retiré avec succès.',
                    'liked' => false,
                    'likes_count' => $post->likes_count,
                    'data' => [
                        'post_id' => $post->id,
                        'liked' => false,
                        'likes_count' => $post->likes_count,
                    ],
                ]);
            }
            else{
                $like=LikeModel::create([
                    'user_id' => $user->id,
                    'post_id' => $postid,
                ]);
            }
            $post->loadCount('likes');
            $this->broadcastLikeUpdated($post->id, $user->id, true, $post->likes_count);
            $this->notifyPostOwner($post, $user->id, $user->name);

            return response()->json([
                'success' => true,
                'message' => 'Post aimé avec succès.',
                'liked' => true,
                'likes_count' => $post->likes_count,
                'data' => [
                    'post_id' => $post->id,
                    'liked' => true,
                    'likes_count' => $post->likes_count,
                ],
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

    private function broadcastLikeUpdated(int $postId, int $userId, bool $liked, int $likesCount): void
    {
        try {
            broadcast(new PostLikeUpdated($postId, $userId, $liked, $likesCount));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function notifyPostOwner(PostModel $post, int $likerId, string $likerName): void
    {
        $ownerUserId = $post->artisanP?->user_id;

        if (! $ownerUserId || (int) $ownerUserId === $likerId) {
            return;
        }

        try {
            app(NotificationService::class)->sendMany([[
                'user_id' => $ownerUserId,
                'type' => 'post_like',
                'data_json' => [
                    'post_id' => $post->id,
                    'liker_id' => $likerId,
                    'liker_name' => $likerName,
                    'likes_count' => $post->likes_count,
                ],
            ]]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
