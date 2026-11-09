<?php

namespace App\Services\JobSources\Adapters;

use App\Enums\RemoteType;
use App\Models\JobSource;
use App\Services\JobSources\Contracts\JobSourceAdapter;
use App\Services\JobSources\Data\JobPostingData;
use App\Services\JobSources\Support\HtmlToText;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Remote OK's public API. Terms: link back to the Remote OK URL and credit
 * "Remote OK" as the source (no logo).
 */
class RemoteOkAdapter implements JobSourceAdapter
{
    private const URL = 'https://remoteok.com/api';

    public function fetch(JobSource $source): iterable
    {
        $tag = trim((string) $source->identifier);

        $items = Http::timeout(30)
            ->retry(2, 1000, throw: false)
            ->get(self::URL, array_filter(['tag' => $tag]))
            ->throw()
            ->json() ?? [];

        foreach ($items as $job) {
            // The first element is the API's legal notice, not a job.
            if (empty($job['id']) || empty($job['position']) || empty($job['url'])) {
                continue;
            }

            // The tag search is loose, so keep only jobs that actually carry the tag.
            if ($tag !== '' && ! $this->mentions($job, $tag)) {
                continue;
            }

            yield new JobPostingData(
                externalId: (string) $job['id'],
                title: trim(html_entity_decode($job['position'])),
                company: $job['company'] ?? 'Unknown company',
                description: HtmlToText::convert($job['description'] ?? ''),
                url: $job['url'],
                location: ($job['location'] ?? '') !== '' ? $job['location'] : 'Remote',
                remoteType: RemoteType::Remote,
                salaryText: empty($job['salary_min']) ? null : 'USD '.number_format($job['salary_min']).' – '.number_format((float) ($job['salary_max'] ?? $job['salary_min'])),
                postedAt: isset($job['date']) ? CarbonImmutable::parse($job['date']) : null,
            );
        }
    }

    /**
     * @param  array<string, mixed>  $job
     */
    private function mentions(array $job, string $tag): bool
    {
        $haystack = Str::lower($job['position'].' '.implode(' ', (array) ($job['tags'] ?? [])));

        return Str::contains($haystack, Str::lower($tag));
    }
}
