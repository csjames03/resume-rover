<?php

namespace App\Models;

use App\Enums\EmploymentType;
use App\Enums\RemoteType;
use Carbon\CarbonImmutable;
use Database\Factories\JobPostingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A job ad as published by a source. Shared by all users; anything personal
 * (score, status) lives on JobMatch and Application.
 *
 * @property int $id
 * @property int $job_source_id
 * @property string $external_id
 * @property string $fingerprint
 * @property string $title
 * @property string $company
 * @property string|null $location
 * @property RemoteType $remote_type
 * @property EmploymentType|null $employment_type
 * @property string|null $salary_text
 * @property string $description
 * @property string $description_hash
 * @property string $url
 * @property CarbonImmutable|null $posted_at
 * @property CarbonImmutable $first_seen_at
 * @property CarbonImmutable $last_seen_at
 * @property CarbonImmutable|null $closed_at
 * @property-read JobSource $source
 */
#[Fillable([
    'job_source_id', 'external_id', 'fingerprint', 'title', 'company', 'location', 'remote_type',
    'employment_type', 'salary_text', 'description', 'description_hash', 'url', 'posted_at',
    'first_seen_at', 'last_seen_at', 'closed_at',
])]
class JobPosting extends Model
{
    /** @use HasFactory<JobPostingFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'remote_type' => RemoteType::class,
            'employment_type' => EmploymentType::class,
            'posted_at' => 'datetime',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<JobSource, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(JobSource::class, 'job_source_id');
    }

    /**
     * @return HasMany<JobRequirement, $this>
     */
    public function requirements(): HasMany
    {
        return $this->hasMany(JobRequirement::class);
    }

    /**
     * @return HasMany<JobMatch, $this>
     */
    public function matches(): HasMany
    {
        return $this->hasMany(JobMatch::class);
    }

    /**
     * @return HasMany<Application, $this>
     */
    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    /**
     * @param  Builder<JobPosting>  $query
     */
    #[Scope]
    protected function open(Builder $query): void
    {
        $query->whereNull('closed_at');
    }

    public function isClosed(): bool
    {
        return $this->closed_at !== null;
    }

    /**
     * Stable identity used to spot the same job published by two different sources.
     */
    public static function fingerprintFor(string $company, string $title, ?string $location): string
    {
        $normalize = fn (?string $value) => preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower((string) $value));

        return hash('sha256', implode('|', [$normalize($company), $normalize($title), $normalize($location)]));
    }
}
