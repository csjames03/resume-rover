<?php

namespace App\Services\Resumes;

use App\Models\Resume;
use App\Models\Skill;
use App\Services\Skills\SkillMatcher;

/**
 * Spots claims in generated text (numbers, skills) that the candidate's own
 * words don't back up. Used by tailoring and by application emails, so
 * "never fabricate" means the same thing everywhere.
 */
class ClaimChecker
{
    public function __construct(private readonly SkillMatcher $skills) {}

    /**
     * Reasons the text needs the user's confirmation; empty when it's fully supported.
     *
     * @param  string  $evidence  the candidate's own text the claim should come from
     * @param  array<int, int>  $resumeSkillIds  skills the candidate listed (always allowed)
     * @return list<string>
     */
    public function unsupported(string $text, string $evidence, array $resumeSkillIds): array
    {
        $reasons = [];

        preg_match_all('/\d+(?:[.,]\d+)?\s*%?/', $text, $numbers);

        foreach (array_unique(array_map('trim', $numbers[0])) as $number) {
            if (! str_contains($evidence, rtrim($number, '%'))) {
                $reasons[] = "Contains \"{$number}\", which isn't in your résumé. Confirm it's accurate.";
            }
        }

        $evidenceSkills = $this->skills->find($evidence);

        foreach ($this->skills->find($text) as $skillId => $term) {
            if (! isset($evidenceSkills[$skillId]) && ! in_array($skillId, $resumeSkillIds, true)) {
                $reasons[] = "Mentions \"{$term}\", which isn't on your résumé. Confirm before using it.";
            }
        }

        return $reasons;
    }

    /**
     * Everything the candidate wrote, as one block of evidence.
     */
    public function resumeText(Resume $resume): string
    {
        $resume->loadMissing('sections.bullets');

        return implode("\n", array_filter([
            $resume->raw_text,
            ...$resume->sections->pluck('description')->all(),
            ...$resume->sections->pluck('title')->all(),
            ...$resume->sections->pluck('organization')->all(),
            ...$resume->sections->flatMap->bullets->pluck('text')->all(),
        ]));
    }

    /**
     * @return array<int, int>
     */
    public function skillIds(Resume $resume): array
    {
        $resume->loadMissing('skills');

        return $resume->skills->map(fn (Skill $skill) => $skill->id)->values()->all();
    }
}
