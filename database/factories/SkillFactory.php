<?php

namespace Database\Factories;

use App\Enums\SkillCategory;
use App\Models\Skill;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Skill>
 */
class SkillFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word().' '.fake()->unique()->numberBetween(1, 99999);

        return [
            'name' => $name,
            'slug' => Skill::slugFor($name),
            'category' => SkillCategory::Tool,
        ];
    }

    /**
     * @param  list<string>  $aliases
     */
    public function named(string $name, array $aliases = []): static
    {
        return $this->state(['name' => $name, 'slug' => Skill::slugFor($name)])
            ->afterCreating(function (Skill $skill) use ($aliases) {
                foreach ($aliases as $alias) {
                    $skill->aliases()->create(['alias' => $alias]);
                }
            });
    }
}
