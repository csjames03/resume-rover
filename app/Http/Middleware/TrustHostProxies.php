<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies;

/**
 * Trusts the host's load balancer (Render) when TRUSTED_PROXIES is set, so
 * HTTPS, secure cookies and client IPs are detected correctly. Read from
 * config at request time, so it still works with the config cached.
 */
class TrustHostProxies extends TrustProxies
{
    /**
     * @return array<int, string>|string|null
     */
    protected function proxies()
    {
        $proxies = trim((string) config('app.trusted_proxies'));

        if ($proxies === '') {
            return null;
        }

        return $proxies === '*' ? '*' : array_values(array_filter(array_map('trim', explode(',', $proxies))));
    }
}
