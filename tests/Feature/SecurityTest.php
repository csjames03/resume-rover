<?php

namespace Tests\Feature;

use App\Models\JobSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admins_can_change_shared_job_sources(): void
    {
        Queue::fake();
        $source = JobSource::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('sources.store'), $this->sourcePayload())->assertForbidden();
        $this->actingAs($user)->put(route('sources.update', $source), $this->sourcePayload())->assertForbidden();
        $this->actingAs($user)->delete(route('sources.destroy', $source))->assertForbidden();
        $this->actingAs($user)->post(route('sources.fetch', $source))->assertForbidden();

        $this->assertDatabaseCount('job_sources', 1);
        Queue::assertNothingPushed();
    }

    public function test_admins_can_add_sources(): void
    {
        Queue::fake();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('sources.store'), $this->sourcePayload())
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('job_sources', ['identifier' => 'acme']);
    }

    public function test_everyone_can_see_sources_but_only_admins_get_the_controls(): void
    {
        $this->actingAs(User::factory()->create())->get(route('sources.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('canManage', false));

        $this->actingAs(User::factory()->admin()->create())->get(route('sources.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('canManage', true));
    }

    public function test_the_admin_flag_cannot_be_set_through_registration_or_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => 'Mallory',
            'email' => $user->email,
            'is_admin' => true,
        ]);

        $this->assertFalse($user->fresh()?->is_admin);
    }

    public function test_admins_are_granted_from_the_command_line(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);

        $this->artisan('users:admin', ['email' => 'OWNER@example.com'])->assertSuccessful();
        $this->assertTrue($user->fresh()?->is_admin);

        $this->artisan('users:admin', ['email' => 'owner@example.com', '--revoke' => true])->assertSuccessful();
        $this->assertFalse($user->fresh()?->is_admin);

        $this->artisan('users:admin', ['email' => 'nobody@example.com'])->assertFailed();
    }

    public function test_pages_send_security_headers_with_a_script_nonce(): void
    {
        $response = $this->get(route('home'))->assertOk();

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        $policy = (string) $response->headers->get('Content-Security-Policy');
        $this->assertMatchesRegularExpression("/script-src 'self' 'nonce-[A-Za-z0-9+\/=]+'/", $policy);
        $this->assertStringContainsString("frame-ancestors 'none'", $policy);
        $this->assertStringContainsString("object-src 'none'", $policy);

        // The inline theme script carries the same nonce, or the browser would block it.
        preg_match("/'nonce-([^']+)'/", $policy, $nonce);
        $this->assertStringContainsString('nonce="'.$nonce[1].'"', (string) $response->getContent());
    }

    public function test_hsts_is_only_sent_over_https(): void
    {
        $this->get(route('home'))->assertHeaderMissing('Strict-Transport-Security');

        $this->get(str_replace('http://', 'https://', route('home')))
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_an_unexpected_appearance_cookie_is_ignored(): void
    {
        $response = $this->withUnencryptedCookie('appearance', "dark';alert(1);//")->get(route('home'));

        $response->assertOk();
        $this->assertStringNotContainsString('alert(1)', (string) $response->getContent());
    }

    /**
     * @return array<string, mixed>
     */
    private function sourcePayload(): array
    {
        return [
            'name' => 'Acme',
            'driver' => 'greenhouse',
            'identifier' => 'acme',
            'fetch_interval_minutes' => 360,
            'is_enabled' => true,
        ];
    }
}
