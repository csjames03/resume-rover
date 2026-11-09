<?php

namespace App\Services\Resumes\Parsing;

use App\Enums\SectionType;
use Carbon\CarbonImmutable;

final class ParsedSection
{
    /**
     * @param  list<string>  $bullets
     */
    public function __construct(
        public SectionType $type,
        public ?string $title = null,
        public ?string $organization = null,
        public ?string $location = null,
        public ?CarbonImmutable $startDate = null,
        public ?CarbonImmutable $endDate = null,
        public bool $isCurrent = false,
        public ?string $description = null,
        public array $bullets = [],
        public ?string $sourceText = null,
        public ?float $confidence = null,
    ) {}
}
