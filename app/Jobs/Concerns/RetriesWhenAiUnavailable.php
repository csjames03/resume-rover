<?php

namespace App\Jobs\Concerns;

use App\Services\Ai\Exceptions\AiUnavailableException;
use Closure;
use Illuminate\Foundation\Queue\Queueable;

/**
 * When every provider is rate-limited or down, wait until one should be
 * back (bounded by the job's $tries) instead of failing straight away.
 *
 * @mixin Queueable
 */
trait RetriesWhenAiUnavailable
{
    private const DEFAULT_RETRY_SECONDS = 60;

    private function withAiRetry(Closure $work): void
    {
        try {
            $work();
        } catch (AiUnavailableException $e) {
            // Missing configuration won't fix itself by waiting.
            if ($e->isUnconfigured() || $this->attempts() >= $this->tries) {
                throw $e;
            }

            $this->release(max(15, $e->retryAfterSeconds ?? self::DEFAULT_RETRY_SECONDS));
        }
    }
}
