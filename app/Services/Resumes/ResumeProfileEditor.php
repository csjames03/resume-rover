<?php

namespace App\Services\Resumes;

use App\Enums\SkillCategory;
use App\Enums\SkillSource;
use App\Jobs\ScoreMatches;
use App\Models\Resume;
use App\Models\ResumeSection;
use App\Models\Skill;
use App\Services\Skills\SkillMatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Every user correction to the parsed profile goes through here, so the
 * profile version is bumped and matches are re-scored in exactly one place.
 */
class ResumeProfileEditor
{
    public function __construct(private readonly SkillMatcher $skills) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $bullets
     */
    public function createSection(Resume $resume, array $attributes, array $bullets): ResumeSection
    {
        return $this->changingProfile($resume, function () use ($resume, $attributes, $bullets) {
            $section = $resume->sections()->create([
                ...$attributes,
                'position' => (int) $resume->sections()->max('position') + 1,
                'is_user_edited' => true,
            ]);

            $this->syncBullets($section, $bullets);

            return $section;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $bullets
     */
    public function updateSection(ResumeSection $section, array $attributes, array $bullets): void
    {
        $this->changingProfile($section->resume, function () use ($section, $attributes, $bullets) {
            $section->update([...$attributes, 'is_user_edited' => true]);
            $this->syncBullets($section, $bullets);
        });
    }

    public function deleteSection(ResumeSection $section): void
    {
        $this->changingProfile($section->resume, fn () => $section->delete());
    }

    /**
     * Attach a skill by name, adding it to the dictionary if it is new.
     */
    public function addSkill(Resume $resume, string $name): Skill
    {
        return $this->changingProfile($resume, function () use ($resume, $name) {
            $skillId = $this->skills->resolve($name);

            $name = Str::limit(trim($name), 60, '');

            $skill = $skillId !== null
                ? Skill::query()->findOrFail($skillId)
                : Skill::query()->firstOrCreate(
                    ['slug' => Skill::slugFor($name)],
                    ['name' => $name, 'category' => SkillCategory::Tool],
                );

            $resume->skills()->syncWithoutDetaching([$skill->id => ['source' => SkillSource::Manual->value]]);

            return $skill;
        });
    }

    public function removeSkill(Resume $resume, Skill $skill): void
    {
        $this->changingProfile($resume, fn () => $resume->skills()->detach($skill->id));
    }

    /**
     * Update bullets in place by position so unchanged lines keep their ids
     * (tailored drafts reference bullet ids as evidence).
     *
     * @param  list<string>  $lines
     */
    private function syncBullets(ResumeSection $section, array $lines): void
    {
        $lines = array_values(array_filter(array_map('trim', $lines), fn (string $line) => $line !== ''));
        $existing = $section->bullets()->get()->values();

        foreach ($lines as $position => $text) {
            $bullet = $existing->get($position);

            $bullet === null
                ? $section->bullets()->create(['position' => $position, 'text' => $text])
                : $bullet->update(['position' => $position, 'text' => $text]);
        }

        $section->bullets()->where('position', '>=', count($lines))->delete();
    }

    /**
     * @template T
     *
     * @param  callable(): T  $change
     * @return T
     */
    private function changingProfile(Resume $resume, callable $change): mixed
    {
        $result = DB::transaction(function () use ($resume, $change) {
            $result = $change();
            $resume->increment('profile_version');

            return $result;
        });

        ScoreMatches::dispatch($resume->user_id);

        return $result;
    }
}
