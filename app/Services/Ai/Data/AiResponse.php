<?php

namespace App\Services\Ai\Data;

/**
 * Raw text returned by one provider call.
 */
final readonly class AiResponse
{
    public function __construct(
        public string $content,
        public ?int $httpStatus = null,
        public ?int $inputTokens = null,
        public ?int $outputTokens = null,
    ) {}
}
