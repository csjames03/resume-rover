<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $user_id
 * @property int $job_posting_id
 * @property int|null $tailored_resume_id
 * @property ApplicationStatus $status
 * @property CarbonImmutable|null $applied_at
 * @property string|null $next_action
 * @property CarbonImmutable|null $next_action_at
 * @property string|null $notes
 * @property CarbonImmutable|null $archived_at
 * @property CarbonImmutable $updated_at
 * @property-read JobPosting $posting
 */
#[Fillable([
    'user_id', 'job_posting_id', 'tailored_resume_id', 'status', 'applied_at',
    'next_action', 'next_action_at', 'notes', 'archived_at',
])]
class Application extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
            'applied_at' => 'datetime',
            'next_action_at' => 'date',
            'archived_at' => 'datetime',
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
     * @return BelongsTo<TailoredResume, $this>
     */
    public function tailoredResume(): BelongsTo
    {
        return $this->belongsTo(TailoredResume::class);
    }

    /**
     * @return HasMany<ApplicationEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(ApplicationEvent::class)->latest('created_at')->latest('id');
    }

    /**
     * @param  Builder<Application>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereNull('archived_at');
    }
}
