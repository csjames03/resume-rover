<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\Email\GoogleOAuth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as GoogleUser;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

/**
 * Connect / disconnect Gmail. Only the send scope is requested.
 */
class EmailConnectionController extends Controller
{
    public function show(Request $request): Response
    {
        $connection = $request->user()->emailConnection;

        return Inertia::render('settings/email', [
            'configured' => GoogleOAuth::isConfigured(),
            'redirectUri' => config('services.google.redirect'),
            'connection' => $connection ? [
                'email' => $connection->email,
                'connected_at' => $connection->created_at?->toIso8601String(),
                'needs_reconnect' => ! $connection->isUsable(),
            ] : null,
        ]);
    }

    public function redirect(): SymfonyRedirect|RedirectResponse
    {
        if (! GoogleOAuth::isConfigured()) {
            $this->toast('Add GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET to .env first.', 'error');

            return to_route('email-connection.show');
        }

        return $this->google()
            ->scopes([GoogleOAuth::GMAIL_SEND_SCOPE])
            // offline + consent: Google returns a refresh token so sending keeps working.
            ->with(['access_type' => 'offline', 'prompt' => 'consent'])
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        if ($request->filled('error')) {
            $this->toast('Gmail was not connected.', 'warning');

            return to_route('email-connection.show');
        }

        try {
            /** @var GoogleUser $google */
            $google = $this->google()->user();
        } catch (InvalidStateException) {
            $this->toast('The sign-in expired or came from another address. Open the app at the same address as APP_URL and try again.', 'error');

            return to_route('email-connection.show');
        } catch (Throwable) {
            $this->toast('Google sign-in failed. Try again.', 'error');

            return to_route('email-connection.show');
        }

        // Google lets people untick individual permissions on the consent screen.
        if (! in_array(GoogleOAuth::GMAIL_SEND_SCOPE, $google->approvedScopes, true)) {
            $this->toast('Gmail was connected without permission to send. Connect again and allow "Send email on your behalf".', 'error');

            return to_route('email-connection.show');
        }

        $user = $request->user();
        $existing = $user->emailConnection;

        $user->emailConnection()->updateOrCreate(['user_id' => $user->id], [
            'provider' => 'google',
            'email' => (string) $google->getEmail(),
            'access_token' => $google->token,
            // Google only sends a refresh token on first consent; keep the old one otherwise.
            'refresh_token' => filled($google->refreshToken) ? $google->refreshToken : $existing?->refresh_token,
            'token_expires_at' => now()->addSeconds($google->expiresIn > 0 ? $google->expiresIn : 3600),
            'scopes' => implode(' ', $google->approvedScopes),
            'needs_reconnect_at' => null,
        ]);

        $this->toast("Gmail connected as {$google->getEmail()}.");

        return to_route('email-connection.show');
    }

    public function destroy(Request $request, GoogleOAuth $oauth): RedirectResponse
    {
        $connection = $request->user()->emailConnection;

        if ($connection !== null) {
            $oauth->revoke($connection);
            $connection->delete();
        }

        $this->toast('Gmail disconnected and access revoked.');

        return to_route('email-connection.show');
    }

    private function google(): GoogleProvider
    {
        /** @var GoogleProvider */
        return Socialite::driver('google');
    }
}
