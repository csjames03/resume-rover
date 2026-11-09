<?php

namespace App\Jobs;

use App\Enums\GenerationStatus;
use App\Jobs\Concerns\RetriesWhenAiUnavailable;
use App\Models\JobMatch;
use App\Services\Matching\MatchExplainer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ExplainMatch implements ShouldQueue
{
    use Queueable, RetriesWhenAiUnavailable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public readonly int $jobMatchId) {}

    public function handle(MatchExplainer $explainer): void
    {
        $match = JobMatch::query()->find($this->jobMatchId);

        if ($match !== null) {
            $this->withAiRetry(fn () => $explainer->explain($match));
        }
    }

    public function failed(?Throwable $exception): void
    {
        JobMatch::query()->whereKey($this->jobMatchId)->update(['explanation_status' => GenerationStatus::Failed]);
    }
}
