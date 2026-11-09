<?php

namespace Tests\Concerns;

use App\Models\Skill;
use App\Services\Skills\SkillMatcher;

trait CreatesSkills
{
    /**
     * A small dictionary: name => aliases.
     *
     * @param  array<string, list<string>>  $skills
     * @return array<string, Skill> keyed by name
     */
    protected function createSkills(array $skills = [
        'PHP' => [],
        'Laravel' => [],
        'PostgreSQL' => ['postgres'],
        'Docker' => [],
        'Kubernetes' => ['k8s'],
        'React' => ['react.js'],
        'TypeScript' => [],
    ]): array
    {
        $created = [];

        foreach ($skills as $name => $aliases) {
            $created[$name] = Skill::factory()->named($name, $aliases)->create();
        }

        SkillMatcher::flush();
        $this->app->forgetScopedInstances();

        return $created;
    }
}
