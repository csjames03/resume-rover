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
 * Jobicy's public remote-jobs API. Terms: credit Jobicy with a direct link
 * and send applicants to the original job URL.
 */
class JobicyAdapter implements JobSourceAdapter
{
    private const URL = 'https://jobicy.com/api/v2/remote-jobs';

    public function fetch(JobSource $source): iterable
    {
        $jobs = Http::timeout(30)
            ->retry(2, 1000, throw: false)
            ->get(self::URL, array_filter(['count' => 100, 'tag' => trim((string) $source->identifier)]))
            ->throw()
            ->json('jobs', []);

        foreach ($jobs as $job) {
            if (empty($job['id']) || empty($job['jobTitle']) || empty($job['url'])) {
                continue;
            }

            yield new JobPostingData(
                externalId: (string) $job['id'],
                title: trim(html_entity_decode($job['jobTitle'])),
                company: $job['companyName'] ?? 'Unknown company',
                description: HtmlToText::convert($job['jobDescription'] ?? ''),
                url: $job['url'],
                location: $job['jobGeo'] ?? null,
                remoteType: RemoteType::Remote,
                employmentType: EmploymentType::infer(implode(' ', (array) ($job['jobType'] ?? []))),
                salaryText: $this->salary($job),
                postedAt: isset($job['pubDate']) ? CarbonImmutable::parse($job['pubDate']) : null,
            );
        }
    }

    /**
     * @param  array<string, mixed>  $job
     */
    private function salary(array $job): ?string
    {
        if (empty($job['salaryMin'])) {
            return null;
        }

        return trim(sprintf(
            '%s %s – %s %s',
            $job['salaryCurrency'] ?? '',
            number_format((float) $job['salaryMin']),
            number_format((float) ($job['salaryMax'] ?? $job['salaryMin'])),
            $job['salaryPeriod'] ?? '',
        ));
    }
}
