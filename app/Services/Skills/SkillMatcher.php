<?php

namespace App\Services\Skills;

use App\Models\Skill;
use App\Models\SkillAlias;
use Illuminate\Support\Facades\Cache;

/**
 * Finds known skills in free text using the skills dictionary and its aliases,
 * so "JS", "javascript" and "JavaScript" all resolve to the same skill.
 */
class SkillMatcher
{
    private const CACHE_KEY = 'skills.dictionary.v1';

    /** @var array{lookup: array<string, int>, terms: array<string, int>, pattern: string|null}|null */
    private ?array $dictionary = null;

    /**
     * Skills mentioned in the text, keyed by skill id, with the term that matched.
     *
     * @return array<int, string>
     */
    public function find(string $text): array
    {
        $dictionary = $this->dictionary();

        if ($dictionary['pattern'] === null || trim($text) === '') {
            return [];
        }

        preg_match_all($dictionary['pattern'], mb_strtolower($text), $matches);

        $found = [];

        foreach ($matches[1] as $term) {
            $skillId = $dictionary['terms'][$term] ?? null;

            if ($skillId !== null && ! isset($found[$skillId])) {
                $found[$skillId] = $term;
            }
        }

        return $found;
    }

    /**
     * Resolve one exact term (typed by the user or returned by AI) to a skill id.
     * Unlike find(), this also accepts ambiguous names such as "Go".
     */
    public function resolve(string $term): ?int
    {
        return $this->dictionary()['lookup'][mb_strtolower(trim($term))] ?? null;
    }

    /**
     * Call after changing skills or aliases.
     */
    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array{lookup: array<string, int>, terms: array<string, int>, pattern: string|null}
     */
    private function dictionary(): array
    {
        return $this->dictionary ??= Cache::rememberForever(self::CACHE_KEY, function () {
            $lookup = [];
            $terms = [];

            foreach (Skill::query()->get(['id', 'name', 'match_by_name']) as $skill) {
                $name = mb_strtolower($skill->name);
                $lookup[$name] = $skill->id;

                if ($skill->match_by_name) {
                    $terms[$name] = $skill->id;
                }
            }

            foreach (SkillAlias::query()->get(['skill_id', 'alias']) as $alias) {
                $lookup[$alias->alias] = $alias->skill_id;
                $terms[$alias->alias] = $alias->skill_id;
            }

            return ['lookup' => $lookup, 'terms' => $terms, 'pattern' => $this->buildPattern(array_keys($terms))];
        });
    }

    /**
     * One alternation regex, longest terms first so "react native" beats "react".
     * Custom boundaries keep "c++", "c#" and ".net" intact (\b would split them).
     *
     * @param  list<string>  $terms
     */
    private function buildPattern(array $terms): ?string
    {
        if ($terms === []) {
            return null;
        }

        usort($terms, fn (string $a, string $b) => mb_strlen($b) <=> mb_strlen($a));

        $alternation = implode('|', array_map(fn (string $term) => preg_quote($term, '/'), $terms));

        return '/(?<![\p{L}\p{N}+#.])('.$alternation.')(?![\p{L}\p{N}+#]|\.[\p{L}\p{N}])/u';
    }
}
