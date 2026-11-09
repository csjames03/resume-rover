<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user's connected Gmail account. Tokens are encrypted at rest and never
 * serialized, so they can't leak into page props or logs.
 *
 * @property int $id
 * @property int $user_id
 * @property string $provider
 * @property string $email
 * @property string $access_token
 * @property string|null $refresh_token
 * @property CarbonImmutable|null $token_expires_at
 * @property string $scopes
 * @property CarbonImmutable|null $needs_reconnect_at
 */
#[Fillable(['user_id', 'provider', 'email', 'access_token', 'refresh_token', 'token_expires_at', 'scopes', 'needs_reconnect_at'])]
#[Hidden(['access_token', 'refresh_token'])]
class EmailConnection extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'needs_reconnect_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isUsable(): bool
    {
        return $this->needs_reconnect_at === null;
    }

    /**
     * Refresh a minute early so a token never expires mid-request.
     */
    public function accessTokenExpired(): bool
    {
        return $this->token_expires_at !== null && $this->token_expires_at->subMinute()->isPast();
    }
}
