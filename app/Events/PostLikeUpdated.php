<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PostLikeUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public int $postId,
        public int $userId,
        public bool $liked,
        public int $likesCount,
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('posts'),
            new Channel('post.' . $this->postId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'post.like.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'post_id' => $this->postId,
            'user_id' => $this->userId,
            'liked' => $this->liked,
            'likes_count' => $this->likesCount,
        ];
    }
}
