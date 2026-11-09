<?php

namespace App\Services\Matching;

use App\Enums\ResumeParseStatus;
use App\Jobs\ScoreMatches;
use App\Models\JobMatch;
use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Applies the scorer to stored postings and saves the results.
 */
class MatchService
{
    private const CHUNK = 200;

    public function __construct(private readonly MatchScorer $scorer) {}

    /**
     * Score open postings for a user (all of them, or only the given ids).
     *
     * @param  list<int>|null  $postingIds
     * @return int number of postings scored
     */
    public function scoreForUser(User $user, ?array $postingIds = null): int
    {
        $user->loadMissing('activeResume', 'preference');
        $resume = $user->activeResume;

        if ($resume === null || ! $resume->isParsed()) {
            return 0;
        }

        $candidate = CandidateProfile::fromResume($resume, $user->preferenceOrDefault());

        // An explanation describes an old profile once the profile changes.
        $user->jobMatches()
            ->where('resume_profile_version', '<', $candidate->profileVersion)
            ->update(['explanation' => null, 'explanation_status' => null, 'explained_at' => null]);

        $scored = 0;

        JobPosting::query()
            ->open()
            ->with('requirements.skill')
            ->when($postingIds !== null, fn (Builder $query) => $query->whereKey($postingIds))
            ->chunkById(self::CHUNK, function (Collection $postings) use ($user, $candidate, &$scored) {
                $now = now();
                $rows = [];

                foreach ($postings as $posting) {
                    $result = $this->scorer->score($candidate, $posting);

                    $rows[] = [
                        'user_id' => $user->id,
                        'job_posting_id' => $posting->id,
                        'resume_id' => $candidate->resumeId,
                        'resume_profile_version' => $candidate->profileVersion,
                        'score' => $result->score,
                        'category' => $result->category->value,
                        'strengths' => json_encode($result->strengths),
                        'gaps' => json_encode($result->gaps),
                        'scoring_version' => config('matching.version'),
                        'scored_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                JobMatch::query()->upsert($rows, ['user_id', 'job_posting_id'], [
                    'resume_id', 'resume_profile_version', 'score', 'category', 'strengths',
                    'gaps', 'scoring_version', 'scored_at', 'updated_at',
                ]);

                $scored += count($rows);
            });

        return $scored;
    }

    /**
     * Queue scoring of new or changed postings for everyone with a parsed résumé.
     *
     * @param  list<int>  $postingIds
     */
    public function queueForAllUsers(array $postingIds): void
    {
        if ($postingIds === []) {
            return;
        }

        User::query()
            ->whereHas('activeResume', fn (Builder $query) => $query->where('parse_status', ResumeParseStatus::Parsed))
            ->pluck('id')
            ->each(fn (int $userId) => ScoreMatches::dispatch($userId, $postingIds));
    }
}
