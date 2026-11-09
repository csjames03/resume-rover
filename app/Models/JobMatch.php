<?php

namespace App\Models;

use App\Enums\GenerationStatus;
use App\Enums\MatchCategory;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @phpstan-type MatchPoint array{type: string, label: string, detail?: string|null, kind?: string|null}
 *
 * @property int $id
 * @property int $user_id
 * @property int $job_posting_id
 * @property int $resume_id
 * @property int $resume_profile_version
 * @property int $score
 * @property MatchCategory $category
 * @property list<MatchPoint> $strengths
 * @property list<MatchPoint> $gaps
 * @property string $scoring_version
 * @property CarbonImmutable $scored_at
 * @property string|null $explanation
 * @property GenerationStatus|null $explanation_status
 * @property CarbonImmutable|null $explained_at
 * @property-read JobPosting $posting
 * @property-read Resume $resume
 */
#[Fillable([
    'user_id', 'job_posting_id', 'resume_id', 'resume_profile_version', 'score', 'category',
    'strengths', 'gaps', 'scoring_version', 'scored_at', 'explanation', 'explanation_status', 'explained_at',
])]
class JobMatch extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => MatchCategory::class,
            'strengths' => 'array',
            'gaps' => 'array',
            'scored_at' => 'datetime',
            'explanation_status' => GenerationStatus::class,
            'explained_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<JobPosting, $this>
     */
    public function posting(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class, 'job_posting_id');
    }

    /**
     * @return BelongsTo<Resume, $this>
     */
    public function resume(): BelongsTo
    {
        return $this->belongsTo(Resume::class);
    }

    /**
     * True when the résumé profile changed after this score was computed.
     */
    public function isStale(): bool
    {
        return $this->resume_profile_version < $this->resume->profile_version;
    }
}
