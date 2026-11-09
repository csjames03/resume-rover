<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Resume|null $activeResume
 * @property-read UserPreference|null $preference
 * @property-read EmailConnection|null $emailConnection
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * @return HasMany<Resume, $this>
     */
    public function resumes(): HasMany
    {
        return $this->hasMany(Resume::class);
    }

    /**
     * The master résumé that matching and tailoring work from.
     *
     * @return HasOne<Resume, $this>
     */
    public function activeResume(): HasOne
    {
        return $this->hasOne(Resume::class)->where('is_active', true);
    }

    /**
     * @return HasOne<UserPreference, $this>
     */
    public function preference(): HasOne
    {
        return $this->hasOne(UserPreference::class);
    }

    /**
     * @return HasMany<JobMatch, $this>
     */
    public function jobMatches(): HasMany
    {
        return $this->hasMany(JobMatch::class);
    }

    /**
     * @return HasMany<Application, $this>
     */
    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    /**
     * @return HasMany<TailoredResume, $this>
     */
    public function tailoredResumes(): HasMany
    {
        return $this->hasMany(TailoredResume::class);
    }

    /**
     * @return HasOne<EmailConnection, $this>
     */
    public function emailConnection(): HasOne
    {
        return $this->hasOne(EmailConnection::class);
    }

    /**
     * @return HasMany<ApplicationEmail, $this>
     */
    public function applicationEmails(): HasMany
    {
        return $this->hasMany(ApplicationEmail::class);
    }

    /**
     * Preferences with sensible defaults when the user hasn't saved any yet.
     */
    public function preferenceOrDefault(): UserPreference
    {
        return $this->preference ?? new UserPreference(['user_id' => $this->id]);
    }
}
