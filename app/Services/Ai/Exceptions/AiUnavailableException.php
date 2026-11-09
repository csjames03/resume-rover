<?php

namespace App\Services\Ai\Exceptions;

use App\Enums\AiTask;
use RuntimeException;

/**
 * No provider could handle the request right now (all failed, benched or unconfigured).
 * Callers should show a "temporarily unavailable" state and retry later.
 */
class AiUnavailableException extends RuntimeException
{
    /**
     * @param  array<string, string>  $failures  provider name => reason
     */
    public function __construct(
        public readonly AiTask $task,
        public readonly array $failures = [],
        public readonly ?int $retryAfterSeconds = null,
    ) {
        $detail = $failures === []
            ? 'no AI provider is configured'
            : collect($failures)->map(fn (string $reason, string $provider) => "{$provider}: {$reason}")->implode('; ');

        parent::__construct("AI is unavailable for [{$task->value}] ({$detail}).");
    }

    /**
     * No provider has an API key in this process, so retrying cannot help.
     */
    public function isUnconfigured(): bool
    {
        return $this->failures === [];
    }

    /**
     * What to tell the user.
     */
    public function userMessage(): string
    {
        if ($this->isUnconfigured()) {
            return 'No AI provider is configured for the background worker. If you just added a key to .env, restart the queue worker.';
        }

        if ($this->failures !== [] && collect($this->failures)->every(fn (string $reason) => $reason === 'too_large')) {
            return "This request is bigger than the free AI tier allows in one minute. Try trimming your résumé's longest sections.";
        }

        $wait = $this->retryAfterSeconds === null ? 'in a few minutes' : 'in '.self::humanize($this->retryAfterSeconds);

        return $this->hitRateLimit()
            ? "The free AI tier is at its limit right now. Try again {$wait}."
            : "The AI providers are unavailable right now. Try again {$wait}.";
    }

    /**
     * Whether at least one provider turned us away for quota rather than an outage.
     */
    public function hitRateLimit(): bool
    {
        return collect($this->failures)->contains(
            fn (string $reason) => in_array($reason, ['rate_limited', 'quota_exhausted', 'cooling down', 'quota used up'], true),
        );
    }

    /**
     * "about 40 seconds", "about 3 minutes", "about 2 hours".
     */
    public static function humanize(int $seconds): string
    {
        return match (true) {
            $seconds < 60 => 'about '.max(5, (int) (ceil($seconds / 5) * 5)).' seconds',
            $seconds < 3600 => 'about '.($minutes = (int) ceil($seconds / 60)).' '.($minutes === 1 ? 'minute' : 'minutes'),
            default => 'about '.($hours = (int) ceil($seconds / 3600)).' '.($hours === 1 ? 'hour' : 'hours'),
        };
    }
}
