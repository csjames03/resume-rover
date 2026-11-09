<?php

namespace App\Services\JobSources\Adapters;

use App\Enums\EmploymentType;
use App\Enums\RemoteType;
use App\Models\JobSource;
use App\Services\JobSources\Contracts\JobSourceAdapter;
use App\Services\JobSources\Data\JobPostingData;
use App\Services\JobSources\Support\HtmlToText;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

/**
 * Ashby's public Job Posting API: api.ashbyhq.com/posting-api/job-board/{company}.
 */
class AshbyAdapter implements JobSourceAdapter
{
    private const URL = 'https://api.ashbyhq.com/posting-api/job-board/%s';

    public function fetch(JobSource $source): iterable
    {
        $jobs = Http::timeout(30)
            ->retry(2, 1000, throw: false)
            ->get(sprintf(self::URL, rawurlencode((string) $source->identifier)), ['includeCompensation' => 'true'])
            ->throw()
            ->json('jobs', []);

        foreach ($jobs as $job) {
            if (empty($job['id']) || empty($job['title']) || empty($job['jobUrl']) || ($job['isListed'] ?? true) === false) {
                continue;
            }

            yield new JobPostingData(
                externalId: (string) $job['id'],
                title: trim($job['title']),
                company: $source->name,
                description: $job['descriptionPlain'] ?? HtmlToText::convert($job['descriptionHtml'] ?? ''),
                url: $job['jobUrl'],
                location: $job['location'] ?? null,
                remoteType: ($job['isRemote'] ?? false) ? RemoteType::Remote : RemoteType::infer($job['workplaceType'] ?? null, $job['location'] ?? null),
                employmentType: EmploymentType::infer($job['employmentType'] ?? null),
                salaryText: ($job['compensation']['compensationTierSummary'] ?? '') ?: null,
                postedAt: isset($job['publishedAt']) ? CarbonImmutable::parse($job['publishedAt']) : null,
            );
        }
    }
}
