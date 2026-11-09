<?php

namespace App\Services\JobSources\Adapters;

use App\Enums\EmploymentType;
use App\Enums\JobSourceDriver;
use App\Enums\RemoteType;
use App\Models\JobSource;
use App\Services\JobSources\Contracts\JobSourceAdapter;
use App\Services\JobSources\Data\JobPostingData;
use App\Services\JobSources\Support\HtmlToText;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

/**
 * Recruitee's public careers API: {company}.recruitee.com/api/offers/.
 */
class RecruiteeAdapter implements JobSourceAdapter
{
    public function fetch(JobSource $source): iterable
    {
        $subdomain = (string) $source->identifier;

        // The identifier becomes part of a hostname, so never trust it blindly.
        if (! preg_match(JobSourceDriver::Recruitee->identifierPattern(), $subdomain)) {
            throw new InvalidArgumentException("Invalid Recruitee subdomain [{$subdomain}].");
        }

        $offers = Http::timeout(30)
            ->retry(2, 1000, throw: false)
            ->get("https://{$subdomain}.recruitee.com/api/offers/")
            ->throw()
            ->json('offers', []);

        foreach ($offers as $offer) {
            if (empty($offer['id']) || empty($offer['title']) || empty($offer['careers_url'])) {
                continue;
            }

            yield new JobPostingData(
                externalId: (string) $offer['id'],
                title: trim($offer['title']),
                company: $offer['company_name'] ?? $source->name,
                description: HtmlToText::convert(($offer['description'] ?? '').'<h3>Requirements</h3>'.($offer['requirements'] ?? '')),
                url: $offer['careers_url'],
                location: $offer['location'] ?? null,
                remoteType: match (true) {
                    (bool) ($offer['remote'] ?? false) => RemoteType::Remote,
                    (bool) ($offer['hybrid'] ?? false) => RemoteType::Hybrid,
                    (bool) ($offer['on_site'] ?? false) => RemoteType::Onsite,
                    default => RemoteType::Unknown,
                },
                employmentType: EmploymentType::infer($offer['employment_type_code'] ?? null),
                salaryText: $this->salary($offer['salary'] ?? null),
                postedAt: isset($offer['published_at']) ? CarbonImmutable::parse($offer['published_at']) : null,
            );
        }
    }

    /**
     * @param  array<string, mixed>|null  $salary
     */
    private function salary(?array $salary): ?string
    {
        if (empty($salary['min']) && empty($salary['max'])) {
            return null;
        }

        return trim(implode(' ', array_filter([
            $salary['currency'] ?? null,
            ($salary['min'] ?? '?').' – '.($salary['max'] ?? '?'),
            isset($salary['period']) ? "per {$salary['period']}" : null,
        ])));
    }
}
