<?php

namespace App\Models;

use App\Enums\TailoredResumeStatus;
use App\Services\Tailoring\TailoredContent;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A job-specific résumé draft. Every bullet in `content` records the master
 * résumé bullet(s) it was derived from.
 *
 * @phpstan-import-type ContentShape from TailoredContent
 *
 * @property int $id
 * @property int $user_id
 * @property int $resume_id
 * @property int $job_posting_id
 * @property int $version
 * @property TailoredResumeStatus $status
 * @property ContentShape|null $content
 * @property list<array{text: string, reason: string}>|null $flagged_claims
 * @property string|null $provider
 * @property string|null $error
 * @property CarbonImmutable|null $approved_at
 * @property CarbonImmutable $created_at
 * @property-read Resume $resume
 * @property-read JobPosting $posting
 */
#[Fillable([
    'user_id', 'resume_id', 'job_posting_id', 'version', 'status', 'content',
    'flagged_claims', 'provider', 'error', 'approved_at',
])]
class TailoredResume extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TailoredResumeStatus::class,
            'content' => 'array',
            'flagged_claims' => 'array',
            'approved_at' => 'datetime',
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
     * @return BelongsTo<Resume, $this>
     */
    public function resume(): BelongsTo
    {
        return $this->belongsTo(Resume::class);
    }

    /**
     * @return BelongsTo<JobPosting, $this>
     */
    public function posting(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class, 'job_posting_id');
    }

    public function isEditable(): bool
    {
        return $this->status === TailoredResumeStatus::Draft;
    }
}
