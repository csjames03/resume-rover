<?php

namespace App\Jobs;

use App\Enums\TailoredResumeStatus;
use App\Jobs\Concerns\RetriesWhenAiUnavailable;
use App\Models\TailoredResume;
use App\Services\Ai\Exceptions\AiUnavailableException;
use App\Services\Tailoring\ResumeTailor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class GenerateTailoredResume implements ShouldQueue
{
    use Queueable, RetriesWhenAiUnavailable;

    public int $tries = 3;

    public int $timeout = 180;

    public function __construct(public readonly int $tailoredResumeId)
    {
        $this->afterCommit();
    }

    public function handle(ResumeTailor $tailor): void
    {
        $tailored = TailoredResume::query()->find($this->tailoredResumeId);

        if ($tailored !== null && $tailored->status === TailoredResumeStatus::Generating) {
            $this->withAiRetry(fn () => $tailor->generate($tailored));
        }
    }

    public function failed(?Throwable $exception): void
    {
        TailoredResume::query()->whereKey($this->tailoredResumeId)->update([
            'status' => TailoredResumeStatus::Failed,
            'error' => $exception instanceof AiUnavailableException
                ? $exception->userMessage()
                : 'Generation failed. Try again.',
        ]);
    }
}
