<?php

namespace App\Services\Ai\Exceptions;

use App\Enums\AiOutcome;
use RuntimeException;

/**
 * A single provider call failed. The outcome decides whether to fail over.
 */
class AiProviderException extends RuntimeException
{
    public function __construct(
        public readonly AiOutcome $outcome,
        string $message,
        public readonly ?int $httpStatus = null,
        public readonly ?int $retryAfterSeconds = null,
    ) {
        parent::__construct($message);
    }
}
