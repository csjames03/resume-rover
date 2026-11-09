<?php

namespace App\Services\Matching\Data;

use App\Enums\MatchCategory;

/**
 * @phpstan-type MatchPoint array{type: string, label: string, detail?: string|null, kind?: string|null}
 */
final readonly class MatchResult
{
    /**
     * @param  list<MatchPoint>  $strengths
     * @param  list<MatchPoint>  $gaps
     */
    public function __construct(
        public int $score,
        public MatchCategory $category,
        public array $strengths,
        public array $gaps,
    ) {}
}
