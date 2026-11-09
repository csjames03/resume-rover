<?php

namespace App\Console\Commands;

use App\Models\JobPosting;
use App\Services\Matching\MatchService;
use App\Services\Matching\RequirementExtractor;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * Re-extract requirements for open postings, e.g. after editing the skills
 * dictionary or improving the extractor, then re-score everyone.
 */
class ReanalyzeJobPostings extends Command
{
    protected $signature = 'postings:reanalyze';

    protected $description = 'Re-extract requirements for open postings and re-score matches';

    public function handle(RequirementExtractor $extractor, MatchService $matches): int
    {
        $ids = [];

        $this->withProgressBar(JobPosting::query()->open()->count(), function ($bar) use ($extractor, &$ids) {
            JobPosting::query()->open()->chunkById(100, function (Collection $postings) use ($extractor, $bar, &$ids) {
                foreach ($postings as $posting) {
                    $extractor->syncFor($posting);
                    $ids[] = $posting->id;
                    $bar->advance();
                }
            });
        });

        $this->newLine();
        $matches->queueForAllUsers($ids);
        $this->components->info(count($ids).' postings re-analyzed; scoring queued.');

        return self::SUCCESS;
    }
}
