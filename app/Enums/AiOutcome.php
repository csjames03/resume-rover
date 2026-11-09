<?php

namespace App\Enums;

/**
 * Result of a single call to an AI provider.
 */
enum AiOutcome: string
{
    use HasValues;

    case Success = 'success';
    case RateLimited = 'rate_limited';
    case QuotaExhausted = 'quota_exhausted';
    case Timeout = 'timeout';
    case ServerError = 'server_error';
    case AuthError = 'auth_error';
    case InvalidRequest = 'invalid_request';
    case InvalidOutput = 'invalid_output';
    // Bigger than this provider's per-minute token allowance; another provider may take it.
    case TooLarge = 'too_large';

    /**
     * Whether trying another provider could succeed where this one failed.
     * An invalid request is our bug, so sending it elsewhere only burns quota.
     */
    public function shouldFailOver(): bool
    {
        return $this !== self::Success && $this !== self::InvalidRequest;
    }

    /**
     * Whether the provider should be benched for a while after this outcome.
     */
    public function triggersCooldown(): bool
    {
        return in_array($this, [
            self::RateLimited,
            self::QuotaExhausted,
            self::Timeout,
            self::ServerError,
            self::AuthError,
        ], true);
    }
}
