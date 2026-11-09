<?php

namespace App\Models;

use App\Services\Skills\SkillMatcher;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $skill_id
 * @property string $alias
 */
#[Fillable(['skill_id', 'alias'])]
class SkillAlias extends Model
{
    protected static function booted(): void
    {
        static::saved(fn () => SkillMatcher::flush());
        static::deleted(fn () => SkillMatcher::flush());
    }

    /**
     * @return BelongsTo<Skill, $this>
     */
    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }

    /**
     * Aliases are matched case-insensitively, so they are stored lowercase.
     *
     * @return Attribute<string, string>
     */
    protected function alias(): Attribute
    {
        return Attribute::make(set: fn (string $value) => mb_strtolower(trim($value)));
    }
}
