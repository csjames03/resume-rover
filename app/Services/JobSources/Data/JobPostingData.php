<?php

namespace App\Services\JobSources\Data;

use App\Enums\EmploymentType;
use App\Enums\RemoteType;
use Carbon\CarbonImmutable;

/**
 * A posting normalized into one shape, whatever source it came from.
 */
final readonly class JobPostingData
{
    public function __construct(
        public string $externalId,
        public string $title,
        public string $company,
        public string $description,
        public string $url,
        public ?string $location = null,
        public RemoteType $remoteType = RemoteType::Unknown,
        public ?EmploymentType $employmentType = null,
        public ?string $salaryText = null,
        public ?CarbonImmutable $postedAt = null,
    ) {}
}
