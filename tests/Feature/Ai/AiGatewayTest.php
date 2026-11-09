<?php

namespace Tests\Feature\Ai;

use App\Enums\AiOutcome;
use App\Enums\AiTask;
use App\Models\AiProviderEvent;
use App\Services\Ai\AiGateway;
use App\Services\Ai\Data\AiRequest;
use App\Services\Ai\Exceptions\AiProviderException;
use App\Services\Ai\Exceptions\AiUnavailableException;
use App\Services\Ai\ProviderCooldowns;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiGatewayTest extends TestCase
{
    use RefreshDatabase;

    private const RULES = ['summary' => ['required', 'string']];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ai.providers.gemini.api_key' => 'test-gemini',
            'ai.providers.groq.api_key' => 'test-groq',
            'ai.providers.openrouter.api_key' => null,
            'ai.priority' => ['gemini', 'groq', 'openrouter'],
        ]);
    }

    public function test_it_fails_over_when_a_provider_is_rate_limited(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'Too many requests']], 429, ['Retry-After' => '120']),
            'api.groq.com/*' => $this->completion(['summary' => 'From Groq']),
        ]);

        $result = $this->gateway()->json($this->request(), self::RULES);

        $this->assertSame('groq', $result->provider);
        $this->assertSame('From Groq', $result->data['summary']);
        $this->assertEquals(
            [AiOutcome::RateLimited, AiOutcome::Success],
            AiProviderEvent::query()->orderBy('id')->pluck('outcome')->all(),
        );
        $this->assertTrue(app(ProviderCooldowns::class)->isCoolingDown('gemini'));
    }

    public function test_a_benched_provider_is_skipped_until_its_cooldown_ends(): void
    {
        app(ProviderCooldowns::class)->bench('gemini', AiOutcome::RateLimited, 60);
        Http::fake(['api.groq.com/*' => $this->completion(['summary' => 'ok'])]);

        $this->gateway()->json($this->request(), self::RULES);

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'googleapis'));
        Http::assertSentCount(1);
    }

    public function test_malformed_output_moves_to_the_next_provider(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => $this->completion(['wrong_key' => true]),
            'api.groq.com/*' => $this->completion(['summary' => 'valid']),
        ]);

        $result = $this->gateway()->json($this->request(), self::RULES);

        $this->assertSame('groq', $result->provider);
        $this->assertSame(AiOutcome::InvalidOutput, AiProviderEvent::query()->orderBy('id')->first()?->outcome);
    }

    public function test_json_wrapped_in_markdown_fences_is_accepted(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'choices' => [['message' => ['content' => "```json\n{\"summary\": \"fenced\"}\n```"]]],
        ])]);

        $this->assertSame('fenced', $this->gateway()->json($this->request(), self::RULES)->data['summary']);
    }

    public function test_an_invalid_request_is_not_retried_elsewhere(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'bad']], 400)]);

        $this->expectException(AiProviderException::class);

        try {
            $this->gateway()->json($this->request(), self::RULES);
        } finally {
            Http::assertSentCount(1);
        }
    }

    public function test_it_reports_unavailable_when_every_provider_fails(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response('', 503),
            'api.groq.com/*' => Http::response('', 503),
        ]);

        try {
            $this->gateway()->json($this->request(), self::RULES);
            $this->fail('Expected AiUnavailableException.');
        } catch (AiUnavailableException $e) {
            $this->assertSame(['gemini' => 'server_error', 'groq' => 'server_error'], $e->failures);
            $this->assertNotNull($e->retryAfterSeconds);
        }
    }

    public function test_no_configured_provider_means_disabled(): void
    {
        config(['ai.providers.gemini.api_key' => null, 'ai.providers.groq.api_key' => null]);

        $this->assertFalse($this->gateway()->isEnabled(AiTask::ExplainMatch));
        $this->expectException(AiUnavailableException::class);
        $this->gateway()->json($this->request(), self::RULES);
    }

    private function gateway(): AiGateway
    {
        return app(AiGateway::class);
    }

    private function request(): AiRequest
    {
        return new AiRequest(AiTask::ExplainMatch, 'system', 'prompt');
    }

    /**
     * @param  array<string, mixed>  $json
     */
    private function completion(array $json): PromiseInterface
    {
        return Http::response([
            'choices' => [['message' => ['content' => json_encode($json)]]],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
        ]);
    }
}
