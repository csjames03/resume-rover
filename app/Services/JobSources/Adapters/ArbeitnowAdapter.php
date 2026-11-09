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
 * Arbeitnow's free job-board API (mostly Europe). It has no search, so it
 * reads the newest few pages; narrow them with the source's title filter.
 */
class ArbeitnowAdapter implements JobSourceAdapter
{
    private const URL = 'https://www.arbeitnow.com/api/job-board-api';

    private const MAX_PAGES = 3;

    public function fetch(JobSource $source): iterable
    {
        for ($page = 1; $page <= self::MAX_PAGES; $page++) {
            $response = Http::timeout(30)
                ->retry(2, 1000, throw: false)
                ->get(self::URL, ['page' => $page])
                ->throw();

            foreach ($response->json('data', []) as $job) {
                if (empty($job['slug']) || empty($job['title']) || empty($job['url'])) {
                    continue;
                }

                yield new JobPostingData(
                    externalId: (string) $job['slug'],
                    title: trim(html_entity_decode($job['title'])),
                    company: $job['company_name'] ?? 'Unknown company',
                    description: HtmlToText::convert($job['description'] ?? ''),
                    url: $job['url'],
                    location: $job['location'] ?? null,
                    remoteType: ($job['remote'] ?? false) ? RemoteType::Remote : RemoteType::infer($job['location'] ?? null),
                    employmentType: EmploymentType::infer(implode(' ', (array) ($job['job_types'] ?? []))),
                    postedAt: isset($job['created_at']) ? CarbonImmutable::createFromTimestamp((int) $job['created_at']) : null,
                );
            }

            if ($response->json('links.next') === null) {
                break;
            }
        }
    }
}
