<?php

namespace Tests\Unit\Ai;

use App\Services\Ai\Quota\RateLimitSnapshot;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RateLimitSnapshotTest extends TestCase
{
    /**
     * @return array<string, array{string, float|null}>
     */
    public static function durations(): array
    {
        return [
            'minutes and seconds' => ['2m59.56s', 179.56],
            'seconds' => ['7.66s', 7.66],
            'milliseconds' => ['120ms', 0.12],
            'hours and minutes' => ['1h2m', 3720.0],
            'plain number' => ['30', 30.0],
            'empty' => ['', null],
            'garbage' => ['soon', null],
        ];
    }

    #[DataProvider('durations')]
    public function test_it_reads_groq_durations(string $value, ?float $seconds): void
    {
        $parsed = RateLimitSnapshot::parseDuration($value);

        $seconds === null ? $this->assertNull($parsed) : $this->assertEqualsWithDelta($seconds, $parsed, 0.001);
    }

    public function test_responses_without_quota_headers_have_no_snapshot(): void
    {
        $this->assertNull(RateLimitSnapshot::fromHeaders(fn () => null, CarbonImmutable::now()));
    }

    public function test_an_exhausted_daily_allowance_blocks_until_it_resets(): void
    {
        $now = CarbonImmutable::parse('2026-11-10 12:00:00');
        $snapshot = $this->snapshot(['x-ratelimit-remaining-requests' => '0', 'x-ratelimit-reset-requests' => '2m30s'], $now);

        $this->assertSame(150, $snapshot->secondsUntilUsable(100, $now));
        $this->assertNull($snapshot->secondsUntilUsable(100, $now->addMinutes(3)), 'stale once the reset time passes');
    }

    public function test_too_few_tokens_left_this_minute_blocks_briefly(): void
    {
        $now = CarbonImmutable::parse('2026-11-10 12:00:00');
        $snapshot = $this->snapshot([
            'x-ratelimit-remaining-requests' => '900',
            'x-ratelimit-limit-tokens' => '12000',
            'x-ratelimit-remaining-tokens' => '1500',
            'x-ratelimit-reset-tokens' => '7.66s',
        ], $now);

        $this->assertNull($snapshot->secondsUntilUsable(1000, $now));
        $this->assertSame(8, $snapshot->secondsUntilUsable(5000, $now));
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function snapshot(array $headers, CarbonImmutable $now): RateLimitSnapshot
    {
        $snapshot = RateLimitSnapshot::fromHeaders(fn (string $name) => $headers[$name] ?? null, $now);
        $this->assertNotNull($snapshot);

        return $snapshot;
    }
}
