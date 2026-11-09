<?php

namespace App\Services\Matching\Data;

use App\Enums\RequirementKind;

final readonly class ExtractedRequirement
{
    public function __construct(
        public RequirementKind $kind,
        public string $rawText,
        public ?int $skillId = null,
        public ?int $minYears = null,
    ) {}
}
