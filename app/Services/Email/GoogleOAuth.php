<?php

namespace App\Services\Email;

use App\Models\EmailConnection;
use App\Services\Email\Exceptions\GmailNotConnected;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Google OAuth bookkeeping: which scope we ask for, keeping the access token
 * fresh, and revoking access on disconnect.
 */
class GoogleOAuth
{
    /** Send-only: the app can never read the user's inbox. */
    public const GMAIL_SEND_SCOPE = 'https://www.googleapis.com/auth/gmail.send';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const REVOKE_URL = 'https://oauth2.googleapis.com/revoke';

    public static function isConfigured(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }

    /**
     * A valid access token, refreshed first if it has expired.
     *
     * @throws GmailNotConnected when Google no longer accepts the refresh token
     */
    public function accessToken(EmailConnection $connection, bool $forceRefresh = false): string
    {
        if (! $connection->isUsable()) {
            throw new GmailNotConnected('Gmail access has expired. Reconnect Gmail in Settings → Email.');
        }

        if ($forceRefresh || $connection->accessTokenExpired()) {
            $this->refresh($connection);
        }

        return $connection->access_token;
    }

    public function revoke(EmailConnection $connection): void
    {
        // Best effort: the local tokens are deleted either way.
        $token = $connection->refresh_token ?? $connection->access_token;
        $response = Http::asForm()->timeout(15)->post(self::REVOKE_URL, ['token' => $token]);

        if ($response->failed()) {
            Log::info('Google token revoke failed', ['status' => $response->status()]);
        }
    }

    private function refresh(EmailConnection $connection): void
    {
        if ($connection->refresh_token === null) {
            $this->markNeedsReconnect($connection);
        }

        $response = Http::asForm()->timeout(15)->post(self::TOKEN_URL, [
            'grant_type' => 'refresh_token',
            'refresh_token' => $connection->refresh_token,
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
        ]);

        // invalid_grant = revoked by the user, or the 7-day limit of an app in "Testing" mode.
        if ($response->status() === 400 || $response->status() === 401) {
            $this->markNeedsReconnect($connection);
        }

        $response->throw();

        $connection->update([
            'access_token' => (string) $response->json('access_token'),
            'token_expires_at' => now()->addSeconds((int) $response->json('expires_in', 3600)),
        ]);
    }

    private function markNeedsReconnect(EmailConnection $connection): never
    {
        $connection->update(['needs_reconnect_at' => now()]);

        throw new GmailNotConnected('Google no longer accepts this connection. Reconnect Gmail in Settings → Email.');
    }
}
