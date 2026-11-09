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
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The single entry point for AI work. Features ask for a task; the gateway
 * picks a healthy provider, validates the JSON it returns, and fails over to
 * the next provider on rate limits, outages or malformed output.
 */
class AiGateway
{
    public function __construct(
        private readonly ProviderRegistry $registry,
        private readonly ProviderCooldowns $cooldowns,
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
        $maxTokens = (int) config("ai.tasks.{$request->task->value}.max_tokens", 2000);
        $failures = [];

        foreach ($providers as $provider) {
            if ($this->cooldowns->isCoolingDown($provider->name())) {
                $failures[$provider->name()] = 'cooling down';

                continue;
            }

            $startedAt = hrtime(true);

            try {
                $response = $provider->complete($request, $maxTokens);
                $data = $this->decodeAndValidate($response->content, $rules);
            } catch (AiProviderException $e) {
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

        throw new AiUnavailableException(
            $request->task,
            $failures,
            $this->cooldowns->secondsUntilAnyAvailable(array_map(fn (AiProvider $p) => $p->name(), $providers)),
        );
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
