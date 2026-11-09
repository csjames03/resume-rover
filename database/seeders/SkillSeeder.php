<?php

namespace Database\Seeders;

use App\Models\Skill;
use App\Services\Skills\SkillMatcher;
use Illuminate\Database\Seeder;

/**
 * Loads database/data/skills.php. Safe to re-run: existing skills are updated.
 */
class SkillSeeder extends Seeder
{
    public function run(): void
    {
        /** @var list<array{0: string, 1: string, 2: list<string>, 3?: bool}> $skills */
        $skills = require database_path('data/skills.php');

        Skill::withoutEvents(function () use ($skills) {
            foreach ($skills as $entry) {
                [$name, $category, $aliases] = $entry;

                $skill = Skill::query()->updateOrCreate(
                    ['slug' => Skill::slugFor($name)],
                    ['name' => $name, 'category' => $category, 'match_by_name' => $entry[3] ?? true],
                );

                $aliases = array_map('mb_strtolower', $aliases);
                $skill->aliases()->whereNotIn('alias', $aliases)->delete();

                foreach ($aliases as $alias) {
                    $skill->aliases()->firstOrCreate(['alias' => $alias]);
                }
            }
        });

        SkillMatcher::flush();
    }
}
