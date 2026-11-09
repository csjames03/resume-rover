<?php

namespace App\Services\Ai\Quota;

use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * The latest quota snapshot per provider. Stored in the cache so the web app
 * and every queue worker see the same numbers.
 */
class ProviderQuotas
{
    public function __construct(private readonly Cache $cache) {}

    public function record(string $provider, RateLimitSnapshot $snapshot): void
    {
        $this->cache->put($this->key($provider), $snapshot->toArray(), now()->addDay());
    }

    public function get(string $provider): ?RateLimitSnapshot
    {
        $data = $this->cache->get($this->key($provider));

        return is_array($data) ? RateLimitSnapshot::fromArray($data) : null;
    }

    private function key(string $provider): string
    {
        return "ai.quota.{$provider}";
    }
}
