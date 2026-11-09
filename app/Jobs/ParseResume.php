<?php

namespace App\Jobs;

use App\Models\Resume;
use App\Services\Resumes\ResumeParsingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ParseResume implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 180;

    public function __construct(public readonly int $resumeId)
    {
        $this->afterCommit();
    }

    public function handle(ResumeParsingService $parser): void
    {
        $resume = Resume::query()->find($this->resumeId);

        if ($resume !== null) {
            $parser->parse($resume);
        }
    }
}
