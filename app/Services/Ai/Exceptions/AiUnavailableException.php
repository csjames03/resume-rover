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
        return $this->isUnconfigured()
            ? 'No AI provider is configured for the background worker. If you just added a key to .env, restart the queue worker.'
            : 'All AI providers are busy or unavailable. Try again in a few minutes.';
    }
}
