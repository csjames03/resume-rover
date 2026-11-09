<?php

namespace App\Services\Tailoring;

use App\Models\TailoredResume;

/**
 * Shape of tailored_resumes.content, plus rendering helpers.
 *
 * @phpstan-type TailoredBullet array{text: string, source_bullet_ids: list<int>, sources: list<string>, flags: list<string>}
 * @phpstan-type TailoredSection array{section_id: int, type: string, title: string|null, organization: string|null, dates: string|null, bullets: list<TailoredBullet>}
 * @phpstan-type ContentShape array{summary: string|null, summary_flags: list<string>, sections: list<TailoredSection>, skills: list<string>}
 */
final class TailoredContent
{
    public static function toMarkdown(TailoredResume $tailored, string $candidateName): string
    {
        $content = $tailored->content ?? ['summary' => null, 'sections' => [], 'skills' => []];
        $lines = ["# {$candidateName}", ''];

        if (! empty($content['summary'])) {
            $lines[] = $content['summary'];
            $lines[] = '';
        }

        if (! empty($content['skills'])) {
            $lines[] = '**Skills:** '.implode(', ', $content['skills']);
            $lines[] = '';
        }

        foreach ($content['sections'] as $section) {
            $heading = implode(' — ', array_filter([$section['title'], $section['organization']]));
            $lines[] = '## '.($heading !== '' ? $heading : ucfirst($section['type']));

            if (! empty($section['dates'])) {
                $lines[] = "_{$section['dates']}_";
            }

            $lines[] = '';

            foreach ($section['bullets'] as $bullet) {
                $lines[] = "- {$bullet['text']}";
            }

            $lines[] = '';
        }

        return implode("\n", $lines);
    }
}
