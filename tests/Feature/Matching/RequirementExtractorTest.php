<?php

namespace Tests\Feature\Matching;

use App\Enums\RequirementKind;
use App\Services\Matching\Data\ExtractedRequirement;
use App\Services\Matching\RequirementExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSkills;
use Tests\TestCase;

class RequirementExtractorTest extends TestCase
{
    use CreatesSkills, RefreshDatabase;

    public function test_headings_decide_must_have_versus_nice_to_have(): void
    {
        $skills = $this->createSkills();

        $description = <<<'TEXT'
            About us
            We use Kubernetes everywhere.

            Requirements:
            • 3+ years building APIs with PHP
            • Experience with Postgres

            Nice to have:
            • Docker
            TEXT;

        $requirements = app(RequirementExtractor::class)->extract('Laravel Engineer', $description);
        $kinds = $this->kindsBySkill($requirements);

        $this->assertSame(RequirementKind::MustHave, $kinds[$skills['Laravel']->id], 'skills in the title are must-haves');
        $this->assertSame(RequirementKind::MustHave, $kinds[$skills['PHP']->id]);
        $this->assertSame(RequirementKind::MustHave, $kinds[$skills['PostgreSQL']->id]);
        $this->assertSame(RequirementKind::NiceToHave, $kinds[$skills['Docker']->id]);
        $this->assertArrayNotHasKey($skills['Kubernetes']->id, $kinds, '"About us" text is ignored');

        $years = collect($requirements)->firstWhere('minYears', '!==', null);
        $this->assertSame(3, $years?->minYears);
    }

    public function test_listed_alternatives_are_not_all_mandatory(): void
    {
        $skills = $this->createSkills();

        $requirements = app(RequirementExtractor::class)->extract(
            'Engineer',
            "What We Require:\nExperience in languages such as PHP, TypeScript, or similar.",
        );

        $kinds = $this->kindsBySkill($requirements);
        $this->assertSame(RequirementKind::NiceToHave, $kinds[$skills['PHP']->id]);
        $this->assertSame(RequirementKind::NiceToHave, $kinds[$skills['TypeScript']->id]);
    }

    /**
     * @param  list<ExtractedRequirement>  $requirements
     * @return array<int, RequirementKind>
     */
    private function kindsBySkill(array $requirements): array
    {
        return collect($requirements)
            ->filter(fn (ExtractedRequirement $r) => $r->skillId !== null)
            ->mapWithKeys(fn (ExtractedRequirement $r) => [$r->skillId => $r->kind])
            ->all();
    }
}
