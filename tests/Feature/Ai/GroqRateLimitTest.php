<?php

namespace Tests\Feature\Ai;

use App\Enums\AiOutcome;
use App\Enums\AiTask;
use App\Enums\MatchCategory;
use App\Jobs\ExplainMatch;
use App\Jobs\GenerateTailoredResume;
use App\Models\ApplicationEmail;
use App\Models\JobMatch;
use App\Models\JobPosting;
use App\Models\Resume;
use App\Models\TailoredResume;
use App\Models\User;
use App\Services\Ai\AiGateway;
use App\Services\Ai\Data\AiRequest;
use App\Services\Ai\Exceptions\AiUnavailableException;
use App\Services\Ai\ProviderCooldowns;
use App\Services\Ai\Quota\ProviderQuotas;
use App\Services\Ai\Quota\RateLimitSnapshot;
use Carbon\CarbonImmutable;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Running on Groq's free tier: read its quota headers, stay under them, and
 * tell users what's going on.
 */
class GroqRateLimitTest extends TestCase
{
    use RefreshDatabase;

    private const RULES = ['summary' => ['required', 'string']];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ai.providers.gemini.api_key' => null,
            'ai.providers.groq.api_key' => 'test-groq',
            'ai.providers.openrouter.api_key' => null,
            'ai.priority' => ['groq'],
            'ai.user_daily_limit' => 30,
        ]);
    }

    public function test_quota_headers_are_remembered_after_each_call(): void
    {
        Http::fake(['api.groq.com/*' => $this->completion(['summary' => 'ok'], [
            'x-ratelimit-limit-requests' => '1000',
            'x-ratelimit-remaining-requests' => '998',
            'x-ratelimit-reset-requests' => '2m52s',
            'x-ratelimit-limit-tokens' => '12000',
            'x-ratelimit-remaining-tokens' => '11800',
            'x-ratelimit-reset-tokens' => '1s',
        ])]);

        app(AiGateway::class)->json($this->request(), self::RULES);

        $quota = app(ProviderQuotas::class)->get('groq');
        $this->assertSame(998, $quota?->remainingRequests);
        $this->assertSame(12000, $quota->limitTokens);
    }

    public function test_groq_is_not_called_once_its_daily_requests_are_used_up(): void
    {
        $this->rememberQuota(['x-ratelimit-remaining-requests' => '0', 'x-ratelimit-reset-requests' => '3m']);
        Http::fake();

        try {
            app(AiGateway::class)->json($this->request(), self::RULES);
            $this->fail('Expected the gateway to give up.');
        } catch (AiUnavailableException $e) {
            $this->assertEqualsWithDelta(180, $e->retryAfterSeconds, 2);
            $this->assertSame('The free AI tier is at its limit right now. Try again in about 3 minutes.', $e->userMessage());
        }

        Http::assertNothingSent();
    }

    public function test_a_429_without_retry_after_backs_off_until_the_reset_header(): void
    {
        Http::fake(['api.groq.com/*' => Http::response(
            ['error' => ['message' => 'Rate limit reached for model on tokens per minute (TPM)']],
            429,
            ['x-ratelimit-remaining-requests' => '500', 'x-ratelimit-remaining-tokens' => '0', 'x-ratelimit-reset-tokens' => '45s'],
        )]);

        try {
            app(AiGateway::class)->json($this->request(), self::RULES);
        } catch (AiUnavailableException) {
            // expected
        }

        $until = app(ProviderCooldowns::class)->until('groq');
        $this->assertNotNull($until);
        $this->assertEqualsWithDelta(45, now()->diffInSeconds($until, absolute: true), 2);
    }

    public function test_the_answer_budget_shrinks_to_fit_the_per_minute_token_limit(): void
    {
        $this->rememberQuota(['x-ratelimit-remaining-requests' => '900', 'x-ratelimit-limit-tokens' => '2000']);
        Http::fake(['api.groq.com/*' => $this->completion(['summary' => 'ok'])]);

        app(AiGateway::class)->json(new AiRequest(AiTask::TailorResume, 'system', 'prompt'), self::RULES);

        // Tailoring asks for 4000 tokens; Groq would reject that against a 2000 TPM limit.
        Http::assertSent(fn (Request $request) => $request['max_tokens'] <= 1900);
    }

    public function test_a_request_too_large_for_groq_fails_over_without_benching_it(): void
    {
        config(['ai.providers.openrouter.api_key' => 'test-openrouter', 'ai.priority' => ['groq', 'openrouter']]);
        Http::fake([
            'api.groq.com/*' => Http::response(['error' => ['message' => 'Request too large for model: Limit 6000, Requested 9000']], 413),
            'openrouter.ai/*' => $this->completion(['summary' => 'from openrouter']),
        ]);

        $result = app(AiGateway::class)->json($this->request(), self::RULES);

        $this->assertSame('openrouter', $result->provider);
        $this->assertFalse(app(ProviderCooldowns::class)->isCoolingDown('groq'));
        $this->assertDatabaseHas('ai_provider_events', ['provider' => 'groq', 'outcome' => AiOutcome::TooLarge->value]);
    }

    public function test_each_user_has_a_daily_ai_allowance(): void
    {
        Queue::fake();
        config(['ai.user_daily_limit' => 2]);
        $user = User::factory()->create();
        $postings = JobPosting::factory()->count(3)->create();
        $postings->each(fn (JobPosting $posting) => $this->match($user, $posting));

        foreach ($postings as $posting) {
            $this->actingAs($user)->post(route('jobs.explanation.store', $posting))->assertRedirect();
        }

        Queue::assertPushed(ExplainMatch::class, 2);
        $this->assertSame(
            ['pending', 'pending', null],
            JobMatch::query()->orderBy('job_posting_id')->pluck('explanation_status')->map(fn ($s) => $s?->value)->all(),
        );
    }

    public function test_tailoring_is_refused_up_front_while_groq_is_resting(): void
    {
        Queue::fake();
        app(ProviderCooldowns::class)->bench('groq', AiOutcome::QuotaExhausted, 600);
        $user = User::factory()->create();
        Resume::factory()->for($user)->create();
        $posting = JobPosting::factory()->create();
        $this->match($user, $posting);

        $this->actingAs($user)->post(route('jobs.tailored-resumes.store', $posting))->assertRedirect();

        $this->assertDatabaseCount(TailoredResume::class, 0);
        Queue::assertNotPushed(GenerateTailoredResume::class);
    }

    public function test_email_drafts_fall_back_to_the_template_when_the_allowance_is_used(): void
    {
        config(['ai.user_daily_limit' => 1]);
        $user = User::factory()->create();
        Resume::factory()->for($user)->create();
        $posting = JobPosting::factory()->create();
        Http::fake(['api.groq.com/*' => $this->completion(['subject' => 'Hello', 'body' => 'From Groq'])]);

        $this->actingAs($user)->post(route('jobs.application-emails.store', $posting));
        $this->actingAs($user)->post(route('jobs.application-emails.store', $posting));

        $this->assertSame(['groq (llama-3.3-70b-versatile)', 'template'], ApplicationEmail::query()->orderBy('id')->pluck('drafted_by')->all());
        Http::assertSentCount(1);
    }

    public function test_the_job_page_shows_the_allowance_and_wait(): void
    {
        config(['ai.user_daily_limit' => 5]);
        app(ProviderCooldowns::class)->bench('groq', AiOutcome::RateLimited, 90);
        $user = User::factory()->create();
        $posting = JobPosting::factory()->create();

        $this->actingAs($user)->get(route('jobs.show', $posting))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('ai.usage.enabled', true)
                ->where('ai.usage.remaining', 5)
                ->where('ai.usage.limit', 5)
                ->where('ai.usage.busy_for', fn (int $seconds) => $seconds > 80 && $seconds <= 90));
    }

    private function request(): AiRequest
    {
        return new AiRequest(AiTask::ExplainMatch, 'system', 'prompt');
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function rememberQuota(array $headers): void
    {
        $snapshot = RateLimitSnapshot::fromHeaders(fn (string $name) => $headers[$name] ?? null, CarbonImmutable::now());
        $this->assertNotNull($snapshot);
        app(ProviderQuotas::class)->record('groq', $snapshot);
    }

    /**
     * @param  array<string, mixed>  $json
     * @param  array<string, string>  $headers
     */
    private function completion(array $json, array $headers = []): PromiseInterface
    {
        return Http::response([
            'choices' => [['message' => ['content' => json_encode($json)]]],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
        ], 200, $headers);
    }

    private function match(User $user, JobPosting $posting): void
    {
        JobMatch::query()->create([
            'user_id' => $user->id,
            'job_posting_id' => $posting->id,
            'resume_id' => Resume::factory()->for($user)->create(['is_active' => false])->id,
            'resume_profile_version' => 1,
            'score' => 70,
            'category' => MatchCategory::fromScore(70),
            'strengths' => [],
            'gaps' => [],
            'scoring_version' => 'test',
            'scored_at' => now(),
        ]);
    }
}
