<?php

namespace Tests\Feature\Email;

use App\Enums\ApplicationEventType;
use App\Enums\ApplicationStatus;
use App\Enums\EmailStatus;
use App\Models\Application;
use App\Models\ApplicationEmail;
use App\Models\EmailConnection;
use App\Models\JobPosting;
use App\Models\Resume;
use App\Models\User;
use App\Services\Email\GoogleOAuth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery;
use Tests\Concerns\CreatesSkills;
use Tests\TestCase;

class ApplicationEmailTest extends TestCase
{
    use CreatesSkills, RefreshDatabase;

    private User $user;

    private Resume $resume;

    private JobPosting $posting;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ai.providers.gemini.api_key' => null,
            'ai.providers.groq.api_key' => null,
            'ai.providers.openrouter.api_key' => null,
            'services.google.client_id' => 'client-id',
            'services.google.client_secret' => 'client-secret',
        ]);

        Storage::fake('local');
        $skills = $this->createSkills();
        $this->user = User::factory()->create(['name' => 'James Ocao']);
        $this->resume = Resume::factory()->for($this->user)->create([
            'storage_path' => 'resumes/1/cv.pdf',
            'original_filename' => 'James_Ocao_Resume.pdf',
            'raw_text' => 'Built Laravel APIs with PHP for 3 years.',
        ]);
        $this->resume->skills()->attach([$skills['PHP']->id, $skills['Laravel']->id], ['source' => 'parsed']);
        Storage::disk('local')->put('resumes/1/cv.pdf', '%PDF-1.4 fake');

        $this->posting = JobPosting::factory()->create([
            'title' => 'Laravel Developer',
            'company' => 'Acme',
            'description' => "Questions? privacy@acme.com\nApply by sending your CV to Jobs@Acme.com.",
        ]);
    }

    public function test_drafting_uses_the_template_without_ai_and_finds_the_recipient(): void
    {
        $this->actingAs($this->user)
            ->post(route('jobs.application-emails.store', $this->posting))
            ->assertRedirect();

        $email = ApplicationEmail::query()->sole();
        $this->assertSame('jobs@acme.com', $email->to, 'privacy@ addresses are skipped');
        $this->assertSame('template', $email->drafted_by);
        $this->assertStringContainsString('Laravel Developer', $email->body);
        $this->assertStringContainsString('James Ocao', $email->body);
        $this->assertSame(EmailStatus::Draft, $email->status);
    }

    public function test_ai_drafts_are_checked_against_the_resume(): void
    {
        config(['ai.providers.gemini.api_key' => 'test']);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['choices' => [['message' => ['content' => json_encode([
            'subject' => 'Laravel Developer application',
            'body' => "Hello,\n\nI built Laravel APIs and ran Kubernetes clusters for 9 years.\n\nJames Ocao",
        ])]]]])]);

        $this->actingAs($this->user)->post(route('jobs.application-emails.store', $this->posting));

        $email = ApplicationEmail::query()->sole();
        $this->assertStringStartsWith('gemini', (string) $email->drafted_by);
        $this->assertCount(2, $email->flags ?? [], 'the 9 years and Kubernetes are not on the résumé');
    }

    public function test_sending_goes_through_gmail_and_marks_the_job_applied(): void
    {
        $this->connectGmail();
        $email = $this->draft();
        Http::fake(['gmail.googleapis.com/*' => Http::response(['id' => 'msg-123'])]);

        $this->actingAs($this->user)
            ->post(route('application-emails.send', $email))
            ->assertSessionHasNoErrors();

        $email->refresh();
        $this->assertSame(EmailStatus::Sent, $email->status, (string) $email->error);
        $this->assertSame('msg-123', $email->gmail_message_id);

        Http::assertSent(function (Request $request) {
            $message = base64_decode(strtr($request['raw'], '-_', '+/'));

            return $request->hasHeader('Authorization', 'Bearer access-token')
                && str_contains($message, 'To: jobs@acme.com')
                && str_contains($message, 'James_Ocao_Resume.pdf');
        });

        $application = Application::query()->sole();
        $this->assertSame(ApplicationStatus::Applied, $application->status);
        $this->assertTrue($application->events()->where('type', ApplicationEventType::EmailSent)->exists());
    }

    public function test_an_email_is_never_sent_without_the_resume_it_promises(): void
    {
        $this->connectGmail();
        $email = $this->draft();
        Storage::disk('local')->delete('resumes/1/cv.pdf'); // e.g. wiped by a host restart
        Http::fake();

        $this->actingAs($this->user)->post(route('application-emails.send', $email));

        $this->assertNotSame(EmailStatus::Sent, $email->refresh()->status);
        $this->assertStringContainsString('no longer stored', (string) $email->error);
        Http::assertNothingSent();
    }

    public function test_resumes_on_a_remote_disk_are_attached_from_the_bucket(): void
    {
        config(['filesystems.resume_disk' => 's3']);
        Storage::fake('s3');
        Storage::disk('s3')->put('resumes/1/cv.pdf', '%PDF-1.4 from the bucket');
        $this->connectGmail();
        $email = $this->draft();
        Http::fake(['gmail.googleapis.com/*' => Http::response(['id' => 'msg-456'])]);

        $this->actingAs($this->user)->post(route('application-emails.send', $email));

        $this->assertSame(EmailStatus::Sent, $email->refresh()->status, (string) $email->error);
        Http::assertSent(fn (Request $request) => str_contains(
            base64_decode(strtr($request['raw'], '-_', '+/')),
            'James_Ocao_Resume.pdf',
        ));
    }

    public function test_an_expired_access_token_is_refreshed_before_sending(): void
    {
        $this->connectGmail(['token_expires_at' => now()->subMinute()]);
        $email = $this->draft();
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'fresh-token', 'expires_in' => 3600]),
            'gmail.googleapis.com/*' => Http::response(['id' => 'msg-1']),
        ]);

        $this->actingAs($this->user)->post(route('application-emails.send', $email));

        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'gmail') && $r->hasHeader('Authorization', 'Bearer fresh-token'));
        $this->assertSame('fresh-token', $this->user->emailConnection()->firstOrFail()->access_token);
        $this->assertSame(EmailStatus::Sent, $email->fresh()?->status);
    }

    public function test_revoked_access_asks_the_user_to_reconnect(): void
    {
        $this->connectGmail(['token_expires_at' => now()->subMinute()]);
        $email = $this->draft();
        Http::fake(['oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant'], 400)]);

        $this->actingAs($this->user)->post(route('application-emails.send', $email))->assertRedirect();

        $this->assertNotNull($this->user->emailConnection()->firstOrFail()->needs_reconnect_at);
        $this->assertSame(EmailStatus::Failed, $email->fresh()?->status);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'gmail.googleapis.com'));
    }

    public function test_nothing_is_sent_without_a_connection_or_a_recipient(): void
    {
        Http::fake();
        $email = $this->draft();

        $this->actingAs($this->user)->post(route('application-emails.send', $email));
        $this->assertSame(EmailStatus::Draft, $email->fresh()?->status);

        $this->connectGmail();
        $email->update(['to' => null]);
        $this->actingAs($this->user)->post(route('application-emails.send', $email));

        $this->assertSame(EmailStatus::Draft, $email->fresh()?->status);
        Http::assertNothingSent();
    }

    public function test_editing_rechecks_claims_and_sent_emails_are_locked(): void
    {
        $email = $this->draft();

        $this->actingAs($this->user)->patch(route('application-emails.update', $email), [
            'to' => 'hr@acme.com',
            'subject' => 'Hello',
            'body' => 'I have 12 years of Kubernetes experience.',
            'attach_resume' => false,
        ])->assertSessionHasNoErrors();

        $this->assertCount(2, $email->fresh()->flags ?? []);

        $email->update(['status' => EmailStatus::Sent]);
        $this->actingAs($this->user)
            ->patch(route('application-emails.update', $email), ['subject' => 'x', 'body' => 'y'])
            ->assertStatus(409);
    }

    public function test_other_users_cannot_see_or_send_my_email(): void
    {
        $email = $this->draft();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get(route('application-emails.show', $email))->assertForbidden();
        $this->actingAs($stranger)->post(route('application-emails.send', $email))->assertForbidden();
    }

    public function test_connecting_gmail_stores_encrypted_tokens(): void
    {
        $this->fakeGoogleUser([GoogleOAuth::GMAIL_SEND_SCOPE, 'openid', 'email']);

        $this->actingAs($this->user)->get(route('email-connection.callback', ['code' => 'x', 'state' => 'y']))->assertRedirect();

        $connection = $this->user->emailConnection()->firstOrFail();
        $this->assertSame('james@gmail.com', $connection->email);
        $this->assertSame('refresh-token', $connection->refresh_token);

        $stored = DB::table('email_connections')->value('refresh_token');
        $this->assertNotSame('refresh-token', $stored, 'tokens are encrypted in the database');
        $this->assertArrayNotHasKey('refresh_token', $connection->toArray(), 'tokens are never serialized');
    }

    public function test_connecting_without_the_send_permission_is_refused(): void
    {
        $this->fakeGoogleUser(['openid', 'email']);

        $this->actingAs($this->user)->get(route('email-connection.callback', ['code' => 'x', 'state' => 'y']));

        $this->assertNull($this->user->emailConnection()->first());
    }

    public function test_disconnecting_revokes_access_at_google(): void
    {
        $this->connectGmail();
        Http::fake(['oauth2.googleapis.com/revoke' => Http::response()]);

        $this->actingAs($this->user)->delete(route('email-connection.destroy'))->assertRedirect();

        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'revoke') && $r['token'] === 'refresh-token');
        $this->assertNull($this->user->emailConnection()->first());
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function connectGmail(array $overrides = []): EmailConnection
    {
        return $this->user->emailConnection()->create([
            'provider' => 'google',
            'email' => 'james@gmail.com',
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'token_expires_at' => now()->addHour(),
            'scopes' => GoogleOAuth::GMAIL_SEND_SCOPE,
            ...$overrides,
        ]);
    }

    private function draft(): ApplicationEmail
    {
        return $this->user->applicationEmails()->create([
            'job_posting_id' => $this->posting->id,
            'resume_id' => $this->resume->id,
            'to' => 'jobs@acme.com',
            'subject' => 'Application: Laravel Developer',
            'body' => "Hello,\n\nI build Laravel APIs.\n\nJames Ocao",
            'attach_resume' => true,
            'status' => EmailStatus::Draft,
        ]);
    }

    /**
     * @param  list<string>  $scopes
     */
    private function fakeGoogleUser(array $scopes): void
    {
        $googleUser = (new GoogleUser)
            ->map(['id' => 'g-1', 'email' => 'james@gmail.com', 'name' => 'James'])
            ->setToken('access-token')
            ->setRefreshToken('refresh-token')
            ->setExpiresIn(3600)
            ->setApprovedScopes($scopes);

        $provider = Mockery::mock(GoogleProvider::class);
        $provider->shouldReceive('user')->andReturn($googleUser);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }
}
