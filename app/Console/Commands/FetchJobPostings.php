<?php

namespace App\Console\Commands;

use App\Jobs\IngestJobSource;
use App\Models\JobSource;
use App\Services\JobSources\JobIngestor;
use Illuminate\Console\Command;
use Throwable;

class FetchJobPostings extends Command
{
    protected $signature = 'postings:fetch
        {source? : Only this job source id}
        {--due : Only sources whose refresh interval has elapsed}
        {--now : Run in this process instead of queueing}
        {--force : Ignore the minimum refresh interval a source\'s terms ask for}';

    protected $description = 'Fetch job postings from the configured sources';

    public function handle(JobIngestor $ingestor): int
    {
        $sources = JobSource::query()
            ->enabled()
            ->when($this->argument('source'), fn ($query, $id) => $query->whereKey($id))
            ->get()
            ->when($this->option('due'), fn ($sources) => $sources->filter(fn (JobSource $source) => $source->isDue()));

        if ($sources->isEmpty()) {
            $this->components->info('No sources to fetch.');

            return self::SUCCESS;
        }

        foreach ($sources as $source) {
            if (! $source->canFetchNow() && ! $this->option('force')) {
                $this->components->warn("{$source->name}: fetched recently; its terms allow one fetch every {$source->driver->minimumIntervalMinutes()} min.");

                continue;
            }

            if (! $this->option('now')) {
                IngestJobSource::dispatch($source->id);
                $this->components->info("Queued {$source->name}.");

                continue;
            }

            try {
                $result = $ingestor->ingest($source);
                $this->components->info("{$source->name}: {$result->summary()}");
            } catch (Throwable $e) {
                $this->components->error("{$source->name}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
