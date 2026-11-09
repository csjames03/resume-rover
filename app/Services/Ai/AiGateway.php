<?php

namespace App\Services\Ai;

use App\Enums\AiOutcome;
use App\Enums\AiTask;
use App\Models\AiProviderEvent;
use App\Services\Ai\Contracts\AiProvider;
use App\Services\Ai\Data\AiRequest;
use App\Services\Ai\Data\AiResponse;
use App\Services\Ai\Data\AiResult;
use App\Services\Ai\Exceptions\AiProviderException;
use App\Services\Ai\Exceptions\AiUnavailableException;
use App\Services\Ai\Quota\ProviderQuotas;
use App\Services\Ai\Quota\RateLimitSnapshot;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The single entry point for AI work. Features ask for a task; the gateway
 * picks a healthy provider, validates the JSON it returns, and fails over to
 * the next provider on rate limits, outages or malformed output.
 *
 * Free tiers are tight, so it also reads each provider's quota headers and
 * skips a provider that would refuse the request, instead of waiting for a 429.
 */
class AiGateway
{
    // Below this there's no room for a useful answer.
    private const MIN_OUTPUT_TOKENS = 300;

    // Headroom for chat formatting and estimation error.
    private const TOKEN_MARGIN = 100;

    public function __construct(
        private readonly ProviderRegistry $registry,
        private readonly ProviderCooldowns $cooldowns,
        private readonly ProviderQuotas $quotas,
    ) {}

    /**
     * Whether at least one provider is configured for the task.
     */
    public function isEnabled(AiTask $task): bool
    {
        return $this->registry->forTask($task) !== [];
    }

    /**
     * @param  array<string, mixed>  $rules  Laravel validation rules the decoded JSON must pass
     *
     * @throws AiProviderException when the request itself is invalid (no point trying elsewhere)
     * @throws AiUnavailableException when no provider could produce valid output
     */
    public function json(AiRequest $request, array $rules): AiResult
    {
        $providers = $this->registry->forTask($request->task);
        $maxTokens = $this->maxTokens($request->task);
        $inputTokens = $this->estimateTokens($request);
        $failures = [];

        foreach ($providers as $provider) {
            $name = $provider->name();

            if ($this->cooldowns->isCoolingDown($name)) {
                $failures[$name] = 'cooling down';

                continue;
            }

            $quota = $this->quotas->get($name);
            $outputTokens = $this->fitToQuota($maxTokens, $inputTokens, $quota);

            if ($outputTokens === null) {
                $failures[$name] = AiOutcome::TooLarge->value;

                continue;
            }

            if ($quota?->secondsUntilUsable($inputTokens + $outputTokens, CarbonImmutable::now()) !== null) {
                $failures[$name] = 'quota used up';

                continue;
            }

            $startedAt = hrtime(true);

            try {
                $response = $provider->complete($request, $outputTokens);
                $this->rememberQuota($name, $response->rateLimit);
                $data = $this->decodeAndValidate($response->content, $rules);
            } catch (AiProviderException $e) {
                $this->rememberQuota($name, $e->rateLimit);
                $this->record($provider, $request->task, $e->outcome, $startedAt, error: $e);

                if ($e->outcome->triggersCooldown()) {
                    $this->cooldowns->bench($provider->name(), $e->outcome, $e->retryAfterSeconds);
                }

                if (! $e->outcome->shouldFailOver()) {
                    throw $e;
                }

                $failures[$provider->name()] = $e->outcome->value;

                continue;
            }

            $this->record($provider, $request->task, AiOutcome::Success, $startedAt, response: $response);
            $this->cooldowns->clear($provider->name());

            return new AiResult($data, $provider->name(), $provider->model());
        }

        $wait = $this->secondsUntilAvailable($request->task);

        throw new AiUnavailableException($request->task, $failures, $wait === 0 ? null : $wait);
    }

    /**
     * Seconds until some provider for this task should accept a typical request:
     * 0 means now, null means no provider is configured. Used to tell users how
     * long to wait before they press the button.
     */
    public function secondsUntilAvailable(AiTask $task): ?int
    {
        $providers = $this->registry->forTask($task);

        if ($providers === []) {
            return null;
        }

        $now = CarbonImmutable::now();
        $needed = $this->maxTokens($task);

        $waits = array_map(function (AiProvider $provider) use ($now, $needed): int {
            $cooldown = $this->cooldowns->until($provider->name());
            $cooldownWait = $cooldown?->isFuture() ? (int) ceil($now->diffInSeconds($cooldown, absolute: true)) : 0;
            $quotaWait = $this->quotas->get($provider->name())?->secondsUntilUsable($needed, $now) ?? 0;

            return max($cooldownWait, $quotaWait);
        }, $providers);

        return min($waits);
    }

    /**
     * Shrinks the answer budget to fit a provider's per-minute token limit,
     * or null when even a short answer wouldn't fit.
     */
    private function fitToQuota(int $maxTokens, int $inputTokens, ?RateLimitSnapshot $quota): ?int
    {
        if ($quota?->limitTokens === null) {
            return $maxTokens;
        }

        $room = $quota->limitTokens - $inputTokens - self::TOKEN_MARGIN;

        return $room < self::MIN_OUTPUT_TOKENS ? null : min($maxTokens, $room);
    }

    /**
     * Rough count (about 3.5 characters per token) for budgeting, not billing.
     */
    private function estimateTokens(AiRequest $request): int
    {
        return (int) ceil(mb_strlen($request->system.$request->prompt) / 3.5);
    }

    private function maxTokens(AiTask $task): int
    {
        return (int) config("ai.tasks.{$task->value}.max_tokens", 2000);
    }

    private function rememberQuota(string $provider, ?RateLimitSnapshot $snapshot): void
    {
        if ($snapshot !== null) {
            $this->quotas->record($provider, $snapshot);
        }
    }

    /**
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    private function decodeAndValidate(string $content, array $rules): array
    {
        // Some models wrap JSON in markdown fences despite being asked not to.
        $json = trim(preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($content)) ?? $content);
        $data = json_decode($json, true);

        if (! is_array($data)) {
            throw new AiProviderException(AiOutcome::InvalidOutput, 'Response was not a JSON object.');
        }

        try {
            Validator::make($data, $rules)->validate();
        } catch (ValidationException $e) {
            throw new AiProviderException(
                AiOutcome::InvalidOutput,
                'Response failed schema validation: '.Str::limit(implode(' ', $e->validator->errors()->all()), 300),
            );
        }

        /** @var array<string, mixed> $data */
        return $data;
    }

    private function record(
        AiProvider $provider,
        AiTask $task,
        AiOutcome $outcome,
        int|float $startedAt,
        ?AiResponse $response = null,
        ?AiProviderException $error = null,
    ): void {
        AiProviderEvent::create([
            'provider' => $provider->name(),
            'model' => $provider->model(),
            'task' => $task,
            'outcome' => $outcome,
            'http_status' => $response->httpStatus ?? $error?->httpStatus,
            'latency_ms' => (int) ((hrtime(true) - $startedAt) / 1_000_000),
            'input_tokens' => $response?->inputTokens,
            'output_tokens' => $response?->outputTokens,
            'error_message' => $error === null ? null : Str::limit($error->getMessage(), 490),
        ]);
    }
}
