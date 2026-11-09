<?php

namespace App\Services\Ai\Quota;

use Carbon\CarbonImmutable;

/**
 * A provider's remaining quota, as reported in its last response headers.
 *
 * Groq (and OpenAI) send `x-ratelimit-*` headers on every reply: on Groq's
 * free tier "requests" is the daily allowance and "tokens" the per-minute
 * allowance. Reading them lets us stop before a 429 instead of after.
 */
final readonly class RateLimitSnapshot
{
    public function __construct(
        public ?int $limitRequests,
        public ?int $remainingRequests,
        public ?CarbonImmutable $requestsResetAt,
        public ?int $limitTokens,
        public ?int $remainingTokens,
        public ?CarbonImmutable $tokensResetAt,
        public CarbonImmutable $observedAt,
    ) {}

    /**
     * @param  callable(string): ?string  $header  reads one response header
     */
    public static function fromHeaders(callable $header, CarbonImmutable $now): ?self
    {
        $snapshot = new self(
            limitRequests: self::int($header('x-ratelimit-limit-requests')),
            remainingRequests: self::int($header('x-ratelimit-remaining-requests')),
            requestsResetAt: self::resetAt($header('x-ratelimit-reset-requests'), $now),
            limitTokens: self::int($header('x-ratelimit-limit-tokens')),
            remainingTokens: self::int($header('x-ratelimit-remaining-tokens')),
            tokensResetAt: self::resetAt($header('x-ratelimit-reset-tokens'), $now),
            observedAt: $now,
        );

        // Providers that don't send these headers (Gemini) simply have no snapshot.
        return $snapshot->remainingRequests === null && $snapshot->remainingTokens === null ? null : $snapshot;
    }

    /**
     * Seconds to wait before a request needing this many tokens can succeed, or null if it can go now.
     * Values whose reset time has passed are stale and ignored.
     */
    public function secondsUntilUsable(int $neededTokens, CarbonImmutable $now): ?int
    {
        $waits = [];

        if ($this->remainingRequests === 0 && $this->requestsResetAt?->isAfter($now)) {
            $waits[] = $this->secondsBetween($now, $this->requestsResetAt);
        }

        if ($this->remainingTokens !== null && $this->remainingTokens < $neededTokens && $this->tokensResetAt?->isAfter($now)) {
            $waits[] = $this->secondsBetween($now, $this->tokensResetAt);
        }

        return $waits === [] ? null : max($waits);
    }

    /**
     * How long to back off after a 429 that came without a Retry-After header.
     */
    public function retryAfterSeconds(CarbonImmutable $now): ?int
    {
        $resetAt = $this->remainingRequests === 0 ? $this->requestsResetAt : $this->tokensResetAt;

        return $resetAt?->isAfter($now) ? $this->secondsBetween($now, $resetAt) : null;
    }

    /**
     * @return array<string, int|string|null>
     */
    public function toArray(): array
    {
        return [
            'limit_requests' => $this->limitRequests,
            'remaining_requests' => $this->remainingRequests,
            'requests_reset_at' => $this->requestsResetAt?->toIso8601String(),
            'limit_tokens' => $this->limitTokens,
            'remaining_tokens' => $this->remainingTokens,
            'tokens_reset_at' => $this->tokensResetAt?->toIso8601String(),
            'observed_at' => $this->observedAt->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, int|string|null>  $data
     */
    public static function fromArray(array $data): self
    {
        $date = fn (string $key) => isset($data[$key]) ? CarbonImmutable::parse((string) $data[$key]) : null;

        return new self(
            limitRequests: self::int($data['limit_requests'] ?? null),
            remainingRequests: self::int($data['remaining_requests'] ?? null),
            requestsResetAt: $date('requests_reset_at'),
            limitTokens: self::int($data['limit_tokens'] ?? null),
            remainingTokens: self::int($data['remaining_tokens'] ?? null),
            tokensResetAt: $date('tokens_reset_at'),
            observedAt: $date('observed_at') ?? CarbonImmutable::now(),
        );
    }

    /**
     * Groq writes durations like "2m59.56s", "7.66s", "1h2m" or "120ms"; plain numbers are seconds.
     */
    public static function parseDuration(?string $value): ?float
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return max(0.0, (float) $value);
        }

        if (! preg_match_all('/(\d+(?:\.\d+)?)(ms|h|m|s)/', $value, $parts, PREG_SET_ORDER)) {
            return null;
        }

        $seconds = 0.0;

        foreach ($parts as [, $amount, $unit]) {
            $seconds += (float) $amount * match ($unit) {
                'h' => 3600,
                'm' => 60,
                's' => 1,
                'ms' => 0.001,
            };
        }

        return $seconds;
    }

    private static function resetAt(?string $value, CarbonImmutable $now): ?CarbonImmutable
    {
        $seconds = self::parseDuration($value);

        return $seconds === null ? null : $now->addMilliseconds((int) ceil($seconds * 1000));
    }

    private function secondsBetween(CarbonImmutable $from, CarbonImmutable $to): int
    {
        return max(1, (int) ceil($from->diffInMilliseconds($to, absolute: true) / 1000));
    }

    private static function int(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
