<?php

namespace Tests\Feature;

use App\Models\JobSource;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Guards against a PostgreSQL server whose local zone isn't UTC shifting
 * every timestamptz value by its offset (it happened: +08:00).
 */
class DatabaseTimezoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_connection_runs_in_utc(): void
    {
        $this->assertSame('UTC', DB::selectOne('show timezone')->TimeZone);
    }

    public function test_timestamps_round_trip_unchanged(): void
    {
        $moment = CarbonImmutable::parse('2026-11-09 06:30:00', 'UTC');
        $source = JobSource::factory()->create(['last_fetched_at' => $moment]);

        $this->assertTrue($source->fresh()?->last_fetched_at?->equalTo($moment));
    }

    public function test_offsets_from_job_boards_are_stored_as_the_same_instant(): void
    {
        $posted = CarbonImmutable::parse('2026-11-01T10:00:00-04:00');
        $source = JobSource::factory()->create(['last_fetched_at' => $posted->utc()]);

        $this->assertSame('2026-11-01T14:00:00+00:00', $source->fresh()?->last_fetched_at?->toIso8601String());
    }
}
