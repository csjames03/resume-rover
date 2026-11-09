<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Matching\MatchService;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Unique per user and posting set until it starts running: a burst of
 * profile edits queues one re-score, and an edit made while it runs
 * queues the next one.
 */
class ScoreMatches implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 600;

    /**
     * @param  list<int>|null  $postingIds  null scores every open posting
     */
    public function __construct(
        public readonly int $userId,
        public readonly ?array $postingIds = null,
    ) {
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return $this->userId.':'.($this->postingIds === null ? 'all' : md5(implode(',', $this->postingIds)));
    }

    public function handle(MatchService $matches): void
    {
        $user = User::query()->find($this->userId);

        if ($user !== null) {
            $matches->scoreForUser($user, $this->postingIds);
        }
    }
}
