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
 * Lever's public Postings API: api.lever.co/v0/postings/{company}.
 */
class LeverAdapter implements JobSourceAdapter
{
    private const URL = 'https://api.lever.co/v0/postings/%s';

    public function fetch(JobSource $source): iterable
    {
        $postings = Http::timeout(30)
            ->retry(2, 1000, throw: false)
            ->get(sprintf(self::URL, rawurlencode((string) $source->identifier)), ['mode' => 'json'])
            ->throw()
            ->json() ?? [];

        foreach ($postings as $posting) {
            if (empty($posting['id']) || empty($posting['text']) || empty($posting['hostedUrl'])) {
                continue;
            }

            $location = $posting['categories']['location'] ?? null;

            yield new JobPostingData(
                externalId: (string) $posting['id'],
                title: trim($posting['text']),
                company: $source->name,
                description: $this->description($posting),
                url: $posting['hostedUrl'],
                location: $location,
                remoteType: RemoteType::infer($posting['workplaceType'] ?? null, $location),
                employmentType: EmploymentType::infer($posting['categories']['commitment'] ?? null),
                salaryText: $this->salary($posting['salaryRange'] ?? null),
                postedAt: isset($posting['createdAt']) ? CarbonImmutable::createFromTimestampMs($posting['createdAt']) : null,
            );
        }
    }

    /**
     * Lever splits the ad into an intro, titled lists (requirements, ...) and a closing.
     *
     * @param  array<string, mixed>  $posting
     */
    private function description(array $posting): string
    {
        $parts = [$posting['descriptionPlain'] ?? HtmlToText::convert($posting['description'] ?? '')];

        foreach ($posting['lists'] ?? [] as $list) {
            $parts[] = ($list['text'] ?? '').":\n".HtmlToText::convert($list['content'] ?? '');
        }

        $parts[] = $posting['additionalPlain'] ?? '';

        return trim(implode("\n\n", array_filter(array_map('trim', $parts))));
    }

    /**
     * @param  array<string, mixed>|null  $range
     */
    private function salary(?array $range): ?string
    {
        if (empty($range['min']) && empty($range['max'])) {
            return null;
        }

        $amount = fn ($value) => $value ? number_format((float) $value) : '?';
        $interval = isset($range['interval']) ? ' '.str_replace('-', ' ', $range['interval']) : '';

        return trim(($range['currency'] ?? '').' '.$amount($range['min'] ?? null).' – '.$amount($range['max'] ?? null).$interval);
    }
}
