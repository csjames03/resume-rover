<?php

namespace Database\Factories;

use App\Enums\SectionType;
use App\Models\Resume;
use App\Models\ResumeSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResumeSection>
 */
class ResumeSectionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'resume_id' => Resume::factory(),
            'type' => SectionType::Experience,
            'position' => 0,
            'title' => fake()->jobTitle(),
            'organization' => fake()->company(),
            'start_date' => now()->subYears(3)->startOfMonth(),
            'end_date' => null,
            'is_current' => true,
        ];
    }

    /**
     * @param  list<string>  $bullets
     */
    public function withBullets(array $bullets): static
    {
        return $this->afterCreating(function (ResumeSection $section) use ($bullets) {
            foreach ($bullets as $position => $text) {
                $section->bullets()->create(['position' => $position, 'text' => $text]);
            }
        });
    }
}
