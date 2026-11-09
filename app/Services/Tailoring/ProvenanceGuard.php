<?php

namespace App\Services\Tailoring;

use App\Models\Resume;
use App\Models\ResumeBullet;
use App\Models\ResumeSection;
use App\Services\Resumes\ClaimChecker;
use App\Services\Skills\SkillMatcher;

/**
 * Enforces "never fabricate". Every tailored bullet must cite real bullets
 * from the master résumé; bullets that cite nothing are dropped, and new
 * numbers or skills that the cited lines don't contain are flagged for the
 * user to confirm.
 *
 * @phpstan-import-type ContentShape from TailoredContent
 * @phpstan-import-type TailoredSection from TailoredContent
 */
class ProvenanceGuard
{
    public function __construct(
        private readonly SkillMatcher $skills,
        private readonly ClaimChecker $claims,
    ) {}

    /**
     * @param  array{summary?: string|null, sections: list<array{section_id: int, bullets?: list<array{text: string, source_bullet_ids?: list<int>}>}>, skills?: list<string>}  $draft
     * @return array{content: ContentShape, flagged: list<array{text: string, reason: string}>}
     */
    public function review(array $draft, Resume $resume): array
    {
        $resume->loadMissing('sections.bullets', 'skills');

        /** @var array<int, ResumeSection> $sections */
        $sections = $resume->sections->keyBy('id')->all();
        /** @var array<int, ResumeBullet> $bullets */
        $bullets = $resume->sections->flatMap->bullets->keyBy('id')->all();
        $resumeSkillIds = $this->claims->skillIds($resume);
        $flagged = [];

        $content = [
            'summary' => null,
            'summary_flags' => [],
            'sections' => [],
            'skills' => [],
        ];

        foreach ($draft['sections'] as $draftSection) {
            $section = $sections[$draftSection['section_id']] ?? null;

            if ($section === null) {
                continue;
            }

            $reviewed = $this->section($section);

            foreach ($draftSection['bullets'] ?? [] as $draftBullet) {
                $text = trim($draftBullet['text']);
                $sourceIds = array_values(array_intersect(array_map('intval', $draftBullet['source_bullet_ids'] ?? []), array_keys($bullets)));

                if ($text === '') {
                    continue;
                }

                if ($sourceIds === []) {
                    $flagged[] = ['text' => $text, 'reason' => 'Not backed by any line in your résumé, so it was left out.'];

                    continue;
                }

                $sources = array_map(fn (int $id) => $bullets[$id]->text, $sourceIds);
                $flags = $this->claims->unsupported($text, implode("\n", $sources), $resumeSkillIds);

                foreach ($flags as $reason) {
                    $flagged[] = ['text' => $text, 'reason' => $reason];
                }

                $reviewed['bullets'][] = ['text' => $text, 'source_bullet_ids' => $sourceIds, 'sources' => $sources, 'flags' => $flags];
            }

            if ($reviewed['bullets'] !== []) {
                $content['sections'][] = $reviewed;
            }
        }

        $summary = trim((string) ($draft['summary'] ?? ''));

        if ($summary !== '') {
            $content['summary'] = $summary;
            $content['summary_flags'] = $this->claims->unsupported($summary, $this->claims->resumeText($resume), $resumeSkillIds);

            foreach ($content['summary_flags'] as $reason) {
                $flagged[] = ['text' => $summary, 'reason' => $reason];
            }
        }

        foreach ($draft['skills'] ?? [] as $skill) {
            $skillId = $this->skills->resolve($skill);

            if ($skillId !== null && in_array($skillId, $resumeSkillIds, true)) {
                $content['skills'][] = $resume->skills->firstWhere('id', $skillId)->name ?? $skill;
            } else {
                $flagged[] = ['text' => $skill, 'reason' => 'Skill is not on your résumé, so it was left out.'];
            }
        }

        $content['skills'] = array_values(array_unique($content['skills']));

        return ['content' => $content, 'flagged' => $flagged];
    }

    /**
     * @return TailoredSection
     */
    private function section(ResumeSection $section): array
    {
        $start = $section->start_date?->format('M Y');
        $end = $section->is_current ? 'Present' : $section->end_date?->format('M Y');

        return [
            'section_id' => $section->id,
            'type' => $section->type->value,
            'title' => $section->title,
            'organization' => $section->organization,
            'dates' => $start === null ? $end : trim($start.' – '.($end ?? '')),
            'bullets' => [],
        ];
    }
}
