<?php

namespace App\Jobs;

use App\Models\JobSource;
use App\Services\JobSources\JobIngestor;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class IngestJobSource implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function __construct(public readonly int $jobSourceId) {}

    public function uniqueId(): string
    {
        return (string) $this->jobSourceId;
    }

    public function handle(JobIngestor $ingestor): void
    {
        $source = JobSource::query()->find($this->jobSourceId);

        if ($source !== null && $source->is_enabled) {
            $ingestor->ingest($source);
        }
    }
}
