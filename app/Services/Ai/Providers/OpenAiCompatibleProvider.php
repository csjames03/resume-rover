<?php

namespace App\Services\Ai\Providers;

use App\Enums\AiOutcome;
use App\Services\Ai\Contracts\AiProvider;
use App\Services\Ai\Data\AiRequest;
use App\Services\Ai\Data\AiResponse;
use App\Services\Ai\Exceptions\AiProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use SensitiveParameter;

/**
 * Talks to any OpenAI-compatible chat completions endpoint
 * (Gemini's compatibility layer, Groq, OpenRouter, ...).
 */
class OpenAiCompatibleProvider implements AiProvider
{
    public function __construct(
        private readonly string $name,
        private readonly string $baseUrl,
        // Hidden from stack traces and error pages.
        #[SensitiveParameter] private readonly string $apiKey,
        private readonly string $model,
        private readonly int $timeoutSeconds,
        private readonly ?string $reasoningEffort = null,
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function model(): string
    {
        return $this->model;
    }

    public function complete(AiRequest $request, int $maxTokens): AiResponse
    {
        try {
            $response = Http::baseUrl($this->baseUrl)
                ->withToken($this->apiKey)
                ->acceptJson()
                ->timeout($this->timeoutSeconds)
                ->post('chat/completions', array_filter([
                    'model' => $this->model,
                    'temperature' => $request->temperature,
                    'max_tokens' => $maxTokens,
                    'reasoning_effort' => $this->reasoningEffort,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        ['role' => 'system', 'content' => $request->system],
                        ['role' => 'user', 'content' => $request->prompt],
                    ],
                ], fn ($value) => $value !== null));
        } catch (ConnectionException $e) {
            throw new AiProviderException(AiOutcome::Timeout, Str::limit($e->getMessage(), 200));
        }

        if ($response->failed()) {
            throw $this->classifyFailure($response);
        }

        $content = $response->json('choices.0.message.content');

        if (! is_string($content) || trim($content) === '') {
            throw new AiProviderException(AiOutcome::InvalidOutput, 'Empty completion.', $response->status());
        }

        return new AiResponse(
            content: $content,
            httpStatus: $response->status(),
            inputTokens: $this->intOrNull($response->json('usage.prompt_tokens')),
            outputTokens: $this->intOrNull($response->json('usage.completion_tokens')),
        );
    }

    private function classifyFailure(Response $response): AiProviderException
    {
        $status = $response->status();
        $message = Str::limit((string) ($response->json('error.message') ?? $response->body()), 300);
        $retryAfter = $this->intOrNull($response->header('Retry-After'));

        $outcome = match (true) {
            $status === 429 && $this->looksLikeQuota($message) => AiOutcome::QuotaExhausted,
            $status === 429 => AiOutcome::RateLimited,
            $status === 402 => AiOutcome::QuotaExhausted,
            in_array($status, [401, 403], true) => AiOutcome::AuthError,
            in_array($status, [400, 413, 422], true) => AiOutcome::InvalidRequest,
            $status === 408 => AiOutcome::Timeout,
            default => AiOutcome::ServerError, // 5xx, or 404 for a model this provider doesn't serve
        };

        return new AiProviderException($outcome, $message, $status, $retryAfter);
    }

    private function looksLikeQuota(string $message): bool
    {
        return Str::contains(Str::lower($message), ['quota', 'resource_exhausted', 'per day', 'daily', 'credits']);
    }

    private function intOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
