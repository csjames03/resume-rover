<?php

namespace App\Models;

use App\Enums\ResumeParseStatus;
use Carbon\CarbonImmutable;
use Database\Factories\ResumeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * @property int $id
 * @property int $user_id
 * @property string $original_filename
 * @property string $storage_path
 * @property string $mime_type
 * @property int $file_size
 * @property string $file_hash
 * @property string|null $raw_text
 * @property ResumeParseStatus $parse_status
 * @property string|null $parse_error
 * @property string|null $parser
 * @property CarbonImmutable|null $parsed_at
 * @property int $profile_version
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property-read User $user
 */
#[Fillable([
    'user_id', 'original_filename', 'storage_path', 'mime_type', 'file_size', 'file_hash',
    'raw_text', 'parse_status', 'parse_error', 'parser', 'parsed_at', 'profile_version', 'is_active',
])]
class Resume extends Model
{
    /** @use HasFactory<ResumeFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'parse_status' => 'pending',
        'profile_version' => 1,
        'is_active' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'parse_status' => ResumeParseStatus::class,
            'parsed_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<ResumeSection, $this>
     */
    public function sections(): HasMany
    {
        return $this->hasMany(ResumeSection::class)->orderBy('position');
    }

    /**
     * @return HasManyThrough<ResumeBullet, ResumeSection, $this>
     */
    public function bullets(): HasManyThrough
    {
        return $this->hasManyThrough(ResumeBullet::class, ResumeSection::class);
    }

    /**
     * @return BelongsToMany<Skill, $this>
     */
    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class)->withPivot('source')->withTimestamps()->orderBy('name');
    }

    public function isParsed(): bool
    {
        return $this->parse_status === ResumeParseStatus::Parsed;
    }

    public function isBeingParsed(): bool
    {
        return in_array($this->parse_status, [ResumeParseStatus::Pending, ResumeParseStatus::Processing], true);
    }
}
