<?php

namespace App\Services\Ai\Quota;

use App\Models\User;
use Illuminate\Cache\RateLimiter;

/**
 * How many AI actions each user may start per day. Everyone shares the app's
 * one API key (and its free-tier quota), so one busy user can't use it all.
 * The window is rolling: it starts with the user's first action.
 */
class UserAiAllowance
{
    private const WINDOW_SECONDS = 86_400;

    public function __construct(private readonly RateLimiter $limiter) {}

    public function limit(): int
    {
        // Always at least one: a 0 or a typo must never mean "unlimited".
        return max(1, (int) config('ai.user_daily_limit'));
    }

    public function remaining(User $user): int
    {
        return $this->limiter->remaining($this->key($user), $this->limit());
    }

    public function hasRemaining(User $user): bool
    {
        return $this->remaining($user) > 0;
    }

    /**
     * Seconds until the window resets (0 when nothing is used yet).
     */
    public function resetsIn(User $user): int
    {
        return $this->limiter->attempts($this->key($user)) === 0 ? 0 : $this->limiter->availableIn($this->key($user));
    }

    public function consume(User $user): void
    {
        $this->limiter->hit($this->key($user), self::WINDOW_SECONDS);
    }

    private function key(User $user): string
    {
        return "ai-allowance:{$user->id}";
    }
}
