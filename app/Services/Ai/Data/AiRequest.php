<?php

namespace App\Services\Ai\Data;

use App\Enums\AiTask;

final readonly class AiRequest
{
    public function __construct(
        public AiTask $task,
        public string $system,
        public string $prompt,
        public float $temperature = 0.2,
    ) {}
}
