<?php

namespace App\Models;

use App\Enums\EmailStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An application email: drafted by the app, edited by the user, sent only
 * when the user clicks Send.
 *
 * @property int $id
 * @property int $user_id
 * @property int $job_posting_id
 * @property int|null $resume_id
 * @property string|null $to
 * @property string $subject
 * @property string $body
 * @property bool $attach_resume
 * @property list<string>|null $flags
 * @property EmailStatus $status
 * @property string|null $drafted_by
 * @property string|null $gmail_message_id
 * @property string|null $error
 * @property CarbonImmutable|null $sent_at
 * @property CarbonImmutable $created_at
 * @property-read JobPosting $posting
 * @property-read Resume|null $resume
 */
#[Fillable([
    'user_id', 'job_posting_id', 'resume_id', 'to', 'subject', 'body', 'attach_resume',
    'flags', 'status', 'drafted_by', 'gmail_message_id', 'error', 'sent_at',
])]
class ApplicationEmail extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attach_resume' => 'boolean',
            'flags' => 'array',
            'status' => EmailStatus::class,
            'sent_at' => 'datetime',
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

    public function isEditable(): bool
    {
        return $this->status->isEditable();
    }
}
