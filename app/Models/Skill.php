<?php

namespace App\Models;

use App\Enums\SkillCategory;
use App\Services\Skills\SkillMatcher;
use Database\Factories\SkillFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property SkillCategory $category
 * @property bool $match_by_name
 * @property-read Pivot|null $pivot present when loaded through resume_skill
 */
#[Fillable(['name', 'slug', 'category', 'match_by_name'])]
class Skill extends Model
{
    /** @use HasFactory<SkillFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saved(fn () => SkillMatcher::flush());
        static::deleted(fn () => SkillMatcher::flush());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => SkillCategory::class,
            'match_by_name' => 'boolean',
        ];
    }

    /**
     * Slug that keeps "C", "C#" and "C++" distinct (Str::slug alone maps all three to "c").
     */
    public static function slugFor(string $name): string
    {
        $spelled = str_replace(['#', '+', '.'], [' sharp ', ' plus ', ' dot '], $name);

        return Str::slug($spelled) ?: md5($name);
    }

    /**
     * @return HasMany<SkillAlias, $this>
     */
    public function aliases(): HasMany
    {
        return $this->hasMany(SkillAlias::class);
    }
}
