<?php

namespace App\Services\Ai;

use App\Enums\AiOutcome;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Carbon;

/**
 * Benches a failing provider for a while so we stop hammering it.
 * Repeated failures back off exponentially (with jitter); a success resets it.
 * Lives in the cache store, so every queue worker sees the same state.
 */
class ProviderCooldowns
{
    public function __construct(private readonly Cache $cache) {}

    public function isCoolingDown(string $provider): bool
    {
        $until = $this->until($provider);

        return $until !== null && $until->isFuture();
    }

    public function until(string $provider): ?Carbon
    {
        $timestamp = $this->cache->get($this->untilKey($provider));

        return is_int($timestamp) ? Carbon::createFromTimestamp($timestamp) : null;
    }

    public function bench(string $provider, AiOutcome $outcome, ?int $retryAfterSeconds = null): void
    {
        $failures = (int) $this->cache->get($this->failuresKey($provider), 0) + 1;
        $seconds = $this->cooldownSeconds($outcome, $failures, $retryAfterSeconds);

        $this->cache->put($this->failuresKey($provider), $failures, now()->addDay());
        $this->cache->put($this->untilKey($provider), now()->addSeconds($seconds)->getTimestamp(), $seconds);
    }

    public function clear(string $provider): void
    {
        $this->cache->forget($this->untilKey($provider));
        $this->cache->forget($this->failuresKey($provider));
    }

    /**
     * Seconds until the first of the given providers becomes usable again.
     *
     * @param  list<string>  $providers
     */
    public function secondsUntilAnyAvailable(array $providers): ?int
    {
        $waits = array_filter(array_map(
            fn (string $provider) => $this->until($provider)?->diffInSeconds(now(), absolute: true),
            $providers,
        ), fn (?float $seconds) => $seconds !== null);

        return $waits === [] ? null : (int) ceil(min($waits));
    }

    private function cooldownSeconds(AiOutcome $outcome, int $failures, ?int $retryAfterSeconds): int
    {
        $config = config('ai.cooldown');

        if ($outcome === AiOutcome::AuthError) {
            return (int) $config['auth_error_seconds'];
        }

        if ($retryAfterSeconds !== null && $retryAfterSeconds > 0) {
            return min($retryAfterSeconds, (int) $config['max_seconds']);
        }

        $exponential = (int) $config['base_seconds'] * (2 ** min($failures - 1, 10));
        $jitter = random_int(0, (int) ($exponential * 0.2));

        return min($exponential + $jitter, (int) $config['max_seconds']);
    }

    private function untilKey(string $provider): string
    {
        return "ai.cooldown.{$provider}.until";
    }

    private function failuresKey(string $provider): string
    {
        return "ai.cooldown.{$provider}.failures";
    }
}
