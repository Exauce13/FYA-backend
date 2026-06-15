<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

class UserPresenceService
{
    private const TTL_SECONDS = 90;

    public function markOnline(User $user): void
    {
        Cache::put($this->cacheKey($user->id), true, now()->addSeconds(self::TTL_SECONDS));
    }

    public function markOffline(User $user): void
    {
        Cache::forget($this->cacheKey($user->id));
    }

    public function isOnline(int $userId): bool
    {
        return Cache::has($this->cacheKey($userId));
    }

    private function cacheKey(int $userId): string
    {
        return "presence:user:{$userId}";
    }
}
