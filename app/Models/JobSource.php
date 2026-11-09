<?php

namespace App\Models;

use App\Enums\JobSourceDriver;
use App\Enums\JobSourceStatus;
use Carbon\CarbonImmutable;
use Database\Factories\JobSourceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property JobSourceDriver $driver
 * @property string|null $identifier
 * @property string|null $title_filter comma-separated words a title must contain one of
 * @property bool $is_enabled
 * @property int $fetch_interval_minutes
 * @property CarbonImmutable|null $last_fetched_at
 * @property JobSourceStatus|null $last_status
 * @property string|null $last_error
 * @property int|null $last_fetched_count
 * @property string|null $compliance_notes
 */
#[Fillable([
    'name', 'driver', 'identifier', 'title_filter', 'is_enabled', 'fetch_interval_minutes', 'last_fetched_at',
    'last_status', 'last_error', 'last_fetched_count', 'compliance_notes',
])]
class JobSource extends Model
{
    /** @use HasFactory<JobSourceFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'driver' => JobSourceDriver::class,
            'is_enabled' => 'boolean',
            'last_fetched_at' => 'datetime',
            'last_status' => JobSourceStatus::class,
        ];
    }

    /**
     * @return HasMany<JobPosting, $this>
     */
    public function postings(): HasMany
    {
        return $this->hasMany(JobPosting::class);
    }

    /**
     * @param  Builder<JobSource>  $query
     */
    #[Scope]
    protected function enabled(Builder $query): void
    {
        $query->where('is_enabled', true);
    }

    /**
     * The interval actually used: never shorter than the source's terms allow.
     */
    public function effectiveIntervalMinutes(): int
    {
        return max($this->fetch_interval_minutes, $this->driver->minimumIntervalMinutes());
    }

    /**
     * Whether the scheduled refresh should run.
     */
    public function isDue(): bool
    {
        return $this->elapsedSinceFetch($this->effectiveIntervalMinutes());
    }

    /**
     * Whether a manual refresh is allowed: only the source's own terms apply.
     */
    public function canFetchNow(): bool
    {
        return $this->elapsedSinceFetch($this->driver->minimumIntervalMinutes());
    }

    /**
     * Whether a posting title passes this source's title filter (no filter = everything passes).
     */
    public function acceptsTitle(string $title): bool
    {
        $words = array_filter(array_map('trim', explode(',', mb_strtolower((string) $this->title_filter))));

        return $words === [] || Str::contains(mb_strtolower($title), $words);
    }

    private function elapsedSinceFetch(int $minutes): bool
    {
        return $this->last_fetched_at === null || $this->last_fetched_at->addMinutes($minutes)->isPast();
    }
}
