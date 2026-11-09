<?php

namespace App\Services\JobSources\Adapters;

use App\Enums\RemoteType;
use App\Models\JobSource;
use App\Services\JobSources\Contracts\JobSourceAdapter;
use App\Services\JobSources\Data\JobPostingData;
use App\Services\JobSources\Support\HtmlToText;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

/**
 * Greenhouse's public Job Board API: boards-api.greenhouse.io/v1/boards/{token}/jobs.
 * No key needed; it exists so companies' openings can be shown elsewhere.
 */
class GreenhouseAdapter implements JobSourceAdapter
{
    private const URL = 'https://boards-api.greenhouse.io/v1/boards/%s/jobs';

    public function fetch(JobSource $source): iterable
    {
        $jobs = Http::timeout(30)
            ->retry(2, 1000, throw: false)
            ->get(sprintf(self::URL, rawurlencode((string) $source->identifier)), ['content' => 'true'])
            ->throw()
            ->json('jobs', []);

        foreach ($jobs as $job) {
            if (empty($job['id']) || empty($job['title']) || empty($job['absolute_url'])) {
                continue;
            }

            $location = $job['location']['name'] ?? null;

            yield new JobPostingData(
                externalId: (string) $job['id'],
                title: trim($job['title']),
                company: $job['company_name'] ?? $source->name,
                description: HtmlToText::convert($job['content'] ?? ''),
                url: $job['absolute_url'],
                location: $location,
                remoteType: RemoteType::infer($location, $job['title']),
                postedAt: isset($job['first_published']) || isset($job['updated_at'])
                    ? CarbonImmutable::parse($job['first_published'] ?? $job['updated_at'])
                    : null,
            );
        }
    }
}
