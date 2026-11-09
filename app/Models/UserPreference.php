<?php

namespace App\Models;

use App\Enums\RemotePreference;
use App\Enums\Seniority;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property list<string>|null $target_titles
 * @property list<string>|null $locations
 * @property RemotePreference $remote_preference
 * @property Seniority|null $seniority
 * @property list<string>|null $excluded_keywords
 */
#[Fillable(['user_id', 'target_titles', 'locations', 'remote_preference', 'seniority', 'excluded_keywords'])]
class UserPreference extends Model
{
    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'remote_preference' => 'any',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'target_titles' => 'array',
            'locations' => 'array',
            'remote_preference' => RemotePreference::class,
            'seniority' => Seniority::class,
            'excluded_keywords' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
