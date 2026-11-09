<?php

namespace Tests\Feature\Tailoring;

use App\Models\Resume;
use App\Models\ResumeSection;
use App\Services\Tailoring\ProvenanceGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSkills;
use Tests\TestCase;

class ProvenanceGuardTest extends TestCase
{
    use CreatesSkills, RefreshDatabase;

    private Resume $resume;

    private ResumeSection $section;

    protected function setUp(): void
    {
        parent::setUp();

        $skills = $this->createSkills();
        $this->resume = Resume::factory()->create();
        $this->resume->skills()->attach($skills['PHP']->id, ['source' => 'parsed']);
        $this->section = ResumeSection::factory()
            ->for($this->resume)
            ->withBullets(['Built PHP APIs used by 3 teams', 'Wrote internal docs'])
            ->create();
    }

    public function test_bullets_without_evidence_are_dropped_and_flagged(): void
    {
        $review = $this->review([
            ['text' => 'Led a team of 12 engineers', 'source_bullet_ids' => []],
            ['text' => 'Invented bullet', 'source_bullet_ids' => [999_999]],
        ]);

        $this->assertSame([], $review['content']['sections']);
        $this->assertCount(2, $review['flagged']);
    }

    public function test_rephrased_bullets_keep_their_sources(): void
    {
        $bulletId = $this->section->bullets()->first()?->id;

        $review = $this->review([['text' => 'Designed PHP APIs adopted by 3 teams', 'source_bullet_ids' => [$bulletId]]]);

        $bullet = $review['content']['sections'][0]['bullets'][0];
        $this->assertSame([$bulletId], $bullet['source_bullet_ids']);
        $this->assertSame(['Built PHP APIs used by 3 teams'], $bullet['sources']);
        $this->assertSame([], $bullet['flags']);
        $this->assertSame([], $review['flagged']);
    }

    public function test_new_numbers_and_skills_are_flagged_for_confirmation(): void
    {
        $bulletId = $this->section->bullets()->first()?->id;

        $review = $this->review([['text' => 'Built PHP and Kubernetes APIs used by 40 teams', 'source_bullet_ids' => [$bulletId]]]);

        $flags = $review['content']['sections'][0]['bullets'][0]['flags'];
        $this->assertCount(2, $flags);
        $this->assertStringContainsString('40', $flags[0]);
        $this->assertStringContainsString('k', strtolower($flags[1]));
    }

    public function test_highlighted_skills_must_be_on_the_resume(): void
    {
        $review = app(ProvenanceGuard::class)->review(
            ['sections' => [], 'skills' => ['php', 'Kubernetes']],
            $this->resume,
        );

        $this->assertSame(['PHP'], $review['content']['skills']);
        $this->assertSame('Kubernetes', $review['flagged'][0]['text']);
    }

    /**
     * @param  list<array{text: string, source_bullet_ids: list<int|null>}>  $bullets
     * @return array{content: array<string, mixed>, flagged: list<array{text: string, reason: string}>}
     */
    private function review(array $bullets): array
    {
        return app(ProvenanceGuard::class)->review(
            ['sections' => [['section_id' => $this->section->id, 'bullets' => $bullets]]],
            $this->resume->fresh() ?? $this->resume,
        );
    }
}
