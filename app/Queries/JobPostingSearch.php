<?php

namespace App\Queries;

use App\Models\JobMatch;
use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * The jobs table: filtering and sorting postings from one user's point of view.
 *
 * @phpstan-type Filters array{q?: string|null, category?: string|null, remote?: string|null, source?: int|null, status?: string|null, include_closed?: bool, sort?: string|null}
 */
class JobPostingSearch
{
    public const SORTS = ['score', 'posted', 'found'];

    public const PER_PAGE = 25;

    /**
     * @param  Filters  $filters
     * @return LengthAwarePaginator<int, JobPosting>
     */
    public function paginate(User $user, array $filters): LengthAwarePaginator
    {
        return $this->query($user, $filters)->paginate(self::PER_PAGE)->withQueryString();
    }

    /**
     * @param  Filters  $filters
     * @return Builder<JobPosting>
     */
    public function query(User $user, array $filters): Builder
    {
        // Used both for eager loads (relations) and whereHas (builders).
        $mine = fn ($query) => $query->where('user_id', $user->id);

        return JobPosting::query()
            ->with(['source', 'matches' => $mine, 'applications' => $mine])
            ->when(! ($filters['include_closed'] ?? false), fn (Builder $q) => $q->open())
            ->when($filters['q'] ?? null, fn (Builder $q, string $term) => $q->where(
                fn (Builder $q) => $q->whereLike('title', "%{$term}%")->orWhereLike('company', "%{$term}%")
            ))
            ->when($filters['category'] ?? null, fn (Builder $q, string $category) => $q->whereHas(
                'matches', fn (Builder $m) => $mine($m)->where('category', $category)
            ))
            ->when($filters['remote'] ?? null, fn (Builder $q, string $remote) => $q->where('remote_type', $remote))
            ->when($filters['source'] ?? null, fn (Builder $q, int $source) => $q->where('job_source_id', $source))
            ->when($filters['status'] ?? null, fn (Builder $q, string $status) => $status === 'untracked'
                ? $q->whereDoesntHave('applications', $mine)
                : $q->whereHas('applications', fn (Builder $a) => $mine($a)->where('status', $status)))
            ->tap(fn (Builder $q) => $this->sort($q, $user, $filters['sort'] ?? 'score'));
    }

    /**
     * @param  Builder<JobPosting>  $query
     */
    private function sort(Builder $query, User $user, string $sort): void
    {
        if ($sort === 'score') {
            // coalesce(max()) turns "no match yet" into -1, so unscored postings sort last
            // (PostgreSQL would otherwise put NULLs first on DESC).
            $query->orderByDesc(
                JobMatch::query()
                    ->selectRaw('coalesce(max(score), -1)')
                    ->whereColumn('job_matches.job_posting_id', 'job_postings.id')
                    ->where('user_id', $user->id),
            );
        } else {
            $query->orderByRaw(($sort === 'posted' ? 'posted_at' : 'first_seen_at').' desc nulls last');
        }

        $query->orderByDesc('job_postings.id');
    }
}
