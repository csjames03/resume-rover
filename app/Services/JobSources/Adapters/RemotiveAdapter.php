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
 * Remotive's public API. Its terms: link back to the Remotive URL, credit
 * Remotive as the source, and fetch at most ~4 times a day.
 */
class RemotiveAdapter implements JobSourceAdapter
{
    private const URL = 'https://remotive.com/api/remote-jobs';

    public function fetch(JobSource $source): iterable
    {
        $jobs = Http::timeout(30)
            ->retry(2, 1000, throw: false)
            ->get(self::URL, array_filter(['search' => $source->identifier, 'limit' => 100]))
            ->throw()
            ->json('jobs', []);

        foreach ($jobs as $job) {
            if (empty($job['id']) || empty($job['title']) || empty($job['url'])) {
                continue;
            }

            yield new JobPostingData(
                externalId: (string) $job['id'],
                title: trim($job['title']),
                company: $job['company_name'] ?? 'Unknown company',
                description: HtmlToText::convert($job['description'] ?? ''),
                url: $job['url'],
                location: $job['candidate_required_location'] ?? null,
                remoteType: RemoteType::Remote,
                employmentType: EmploymentType::infer($job['job_type'] ?? null),
                salaryText: ($job['salary'] ?? '') !== '' ? $job['salary'] : null,
                postedAt: isset($job['publication_date']) ? CarbonImmutable::parse($job['publication_date']) : null,
            );
        }
    }
}
