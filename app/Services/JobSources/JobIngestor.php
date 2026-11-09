<?php

namespace App\Services\JobSources;

use App\Enums\JobSourceStatus;
use App\Models\JobPosting;
use App\Models\JobSource;
use App\Services\JobSources\Contracts\JobSourceAdapter;
use App\Services\JobSources\Data\JobPostingData;
use App\Services\Matching\MatchService;
use App\Services\Matching\RequirementExtractor;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Str;
use Throwable;

/**
 * Fetches one source and reconciles it with the database. Safe to re-run:
 * the same posting is matched on (source, external id), so retries never
 * create duplicates.
 */
class JobIngestor
{
    public function __construct(
        private readonly Container $container,
        private readonly RequirementExtractor $requirements,
        private readonly MatchService $matches,
    ) {}

    public function ingest(JobSource $source): IngestResult
    {
        $startedAt = CarbonImmutable::now();
        $result = new IngestResult;

        try {
            /** @var JobSourceAdapter $adapter */
            $adapter = $this->container->make($source->driver->adapter());

            foreach ($adapter->fetch($source) as $data) {
                $result->fetched++;

                // Filtered-out postings count as "not seen", so ones stored before the filter get closed.
                if (! $source->acceptsTitle($data->title)) {
                    $result->filtered++;

                    continue;
                }

                $this->reconcile($source, $data, $startedAt, $result);
            }

            // An empty response is more likely an outage than every job closing at once.
            if ($result->fetched > 0) {
                $result->closed = $source->postings()->open()->where('last_seen_at', '<', $startedAt)->update(['closed_at' => $startedAt]);
            }
        } catch (Throwable $e) {
            $source->update([
                'last_fetched_at' => $startedAt,
                'last_status' => JobSourceStatus::Failed,
                'last_error' => Str::limit($e->getMessage(), 500),
            ]);

            throw $e;
        }

        $source->update([
            'last_fetched_at' => $startedAt,
            'last_status' => JobSourceStatus::Ok,
            'last_error' => null,
            'last_fetched_count' => $result->fetched,
        ]);

        $this->matches->queueForAllUsers($result->changedPostingIds);

        return $result;
    }

    private function reconcile(JobSource $source, JobPostingData $data, CarbonImmutable $now, IngestResult $result): void
    {
        $fingerprint = JobPosting::fingerprintFor($data->company, $data->title, $data->location);
        $descriptionHash = hash('sha256', $data->title."\n".$data->description);

        $posting = $source->postings()->where('external_id', $data->externalId)->first();

        if ($posting === null && $this->publishedByAnotherSource($source, $fingerprint)) {
            $result->duplicates++;

            return;
        }

        $attributes = [
            'fingerprint' => $fingerprint,
            'title' => Str::limit($data->title, 250, ''),
            'company' => Str::limit($data->company, 250, ''),
            'location' => $data->location === null ? null : Str::limit($data->location, 250, ''),
            'remote_type' => $data->remoteType,
            'employment_type' => $data->employmentType,
            'salary_text' => $data->salaryText === null ? null : Str::limit($data->salaryText, 250, ''),
            'description' => $data->description,
            'description_hash' => $descriptionHash,
            'url' => $data->url,
            // Sources report their own offsets (-04:00, +00:00…); store the UTC instant.
            'posted_at' => $data->postedAt?->utc(),
            'last_seen_at' => $now,
            'closed_at' => null,
        ];

        if ($posting === null) {
            $posting = $source->postings()->create([
                ...$attributes,
                'external_id' => $data->externalId,
                'first_seen_at' => $now,
            ]);
            $result->created++;
        } else {
            $changed = $posting->description_hash !== $descriptionHash;
            $posting->update($attributes);

            if (! $changed) {
                return;
            }

            $result->updated++;
        }

        $this->requirements->syncFor($posting);
        $result->changedPostingIds[] = $posting->id;
    }

    /**
     * The same job syndicated to two boards should show up once.
     */
    private function publishedByAnotherSource(JobSource $source, string $fingerprint): bool
    {
        return JobPosting::query()
            ->open()
            ->where('fingerprint', $fingerprint)
            ->where('job_source_id', '!=', $source->id)
            ->exists();
    }
}
