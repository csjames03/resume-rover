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
 * Himalayas' public remote-jobs API. With keywords it uses the search
 * endpoint; without, the latest jobs feed.
 */
class HimalayasAdapter implements JobSourceAdapter
{
    private const FEED_URL = 'https://himalayas.app/jobs/api';

    private const SEARCH_URL = 'https://himalayas.app/jobs/api/search';

    public function fetch(JobSource $source): iterable
    {
        $query = trim((string) $source->identifier);

        $jobs = Http::timeout(30)
            ->retry(2, 1000, throw: false)
            ->get($query === '' ? self::FEED_URL : self::SEARCH_URL, $query === '' ? ['limit' => 100] : ['q' => $query])
            ->throw()
            ->json('jobs', []);

        foreach ($jobs as $job) {
            // guid is the job's permanent URL, so it doubles as its id.
            $url = $job['applicationLink'] ?? $job['guid'] ?? null;

            if (empty($job['guid']) || empty($job['title']) || empty($url)) {
                continue;
            }

            $restrictions = (array) ($job['locationRestrictions'] ?? []);

            yield new JobPostingData(
                externalId: md5($job['guid']),
                title: trim(html_entity_decode($job['title'])),
                company: $job['companyName'] ?? 'Unknown company',
                description: HtmlToText::convert($job['description'] ?? ''),
                url: $url,
                location: $restrictions === [] ? 'Remote, worldwide' : 'Remote: '.implode(', ', $restrictions),
                remoteType: RemoteType::Remote,
                employmentType: EmploymentType::infer($job['employmentType'] ?? null),
                salaryText: empty($job['minSalary']) ? null : trim(($job['currency'] ?? '').' '.number_format((float) $job['minSalary']).' – '.number_format((float) ($job['maxSalary'] ?? $job['minSalary']))),
                postedAt: isset($job['pubDate']) ? CarbonImmutable::createFromTimestamp((int) $job['pubDate']) : null,
            );
        }
    }
}
