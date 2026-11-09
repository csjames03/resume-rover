<?php

namespace App\Services\Matching;

use App\Enums\RequirementKind;
use App\Models\JobPosting;
use App\Services\Matching\Data\ExtractedRequirement;
use App\Services\Skills\SkillMatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Reads a posting and lists what it asks for. Runs once per posting (not per
 * user), so it stays cheap no matter how many people are matched against it.
 *
 * Must-have vs nice-to-have is decided by the heading a line sits under
 * ("Requirements", "Nice to have", ...) and by wording on the line itself.
 */
class RequirementExtractor
{
    public const VERSION = 'keyword-v1';

    private const NICE = '/nice[\s-]to[\s-]have|bonus|preferred|a plus|is a plus|desired|good to have|extra credit|ideally|familiarity|we value/i';

    private const MUST = '/requir|must|essential|qualifications|what you(?:\'|’)ll need|what we(?:\'|’)re looking for|you have|you bring|about you|who you are|minimum|proficien|strong|solid/i';

    /** "Java, Python, or similar" lists alternatives, so no single one is mandatory. */
    private const ALTERNATIVES = '/such as|or similar|or equivalent|e\.g\.|for example|one or more of|any of/i';

    private const DUTIES = '/responsibilit|what you(?:\'|’)ll do|the role|your role|day[\s-]to[\s-]day|what you will do/i';

    private const IGNORED = '/benefit|perks|we offer|about us|about the company|compensation|salary|equal opportunity|why join|life at/i';

    public function __construct(private readonly SkillMatcher $skills) {}

    /**
     * @return list<ExtractedRequirement>
     */
    public function extract(string $title, string $description): array
    {
        /** @var array<int, ExtractedRequirement> $bySkill */
        $bySkill = [];
        $years = null;

        // Skills in the job title are always central to the role.
        foreach (array_keys($this->skills->find($title)) as $skillId) {
            $bySkill[$skillId] = new ExtractedRequirement(RequirementKind::MustHave, $title, $skillId);
        }

        $context = null;

        foreach ($this->lines($description) as $line) {
            if ($this->isHeading($line)) {
                $context = $this->contextFor($line);

                continue;
            }

            if ($context === 'ignored') {
                continue;
            }

            $kind = $this->kindFor($line, $context);

            foreach (array_keys($this->skills->find($line)) as $skillId) {
                $existing = $bySkill[$skillId] ?? null;

                if ($existing === null || ($existing->kind === RequirementKind::NiceToHave && $kind === RequirementKind::MustHave)) {
                    $bySkill[$skillId] = new ExtractedRequirement($kind, Str::limit($line, 300), $skillId);
                }
            }

            $minYears = $this->yearsIn($line);

            if ($minYears !== null && ($years === null || $minYears > $years->minYears)) {
                $years = new ExtractedRequirement($kind, Str::limit($line, 300), minYears: $minYears);
            }
        }

        return array_values(array_filter([...array_values($bySkill), $years]));
    }

    /**
     * Replace a posting's stored requirements with a fresh extraction.
     */
    public function syncFor(JobPosting $posting): void
    {
        $requirements = $this->extract($posting->title, $posting->description);

        DB::transaction(function () use ($posting, $requirements) {
            $posting->requirements()->delete();

            $posting->requirements()->createMany(array_map(fn (ExtractedRequirement $requirement) => [
                'skill_id' => $requirement->skillId,
                'kind' => $requirement->kind,
                'min_years' => $requirement->minYears,
                'raw_text' => $requirement->rawText,
                'extractor_version' => self::VERSION,
            ], $requirements));
        });
    }

    /**
     * @return list<string>
     */
    private function lines(string $description): array
    {
        $lines = [];

        foreach (preg_split('/\R/u', $description) ?: [] as $line) {
            $line = trim((string) preg_replace('/^[•\-*·\s]+/u', '', $line));

            if ($line !== '') {
                // Long paragraphs are split into sentences so wording applies locally.
                array_push($lines, ...(mb_strlen($line) > 250 ? preg_split('/(?<=[.!?])\s+/u', $line) ?: [$line] : [$line]));
            }
        }

        return $lines;
    }

    private function isHeading(string $line): bool
    {
        $short = mb_strlen($line) <= 60 && str_word_count($line) <= 8;

        return $short && (str_ends_with($line, ':') || ! preg_match('/[.!?,;]$/u', $line))
            && (preg_match(self::NICE, $line) || preg_match(self::MUST, $line) || preg_match(self::DUTIES, $line) || preg_match(self::IGNORED, $line));
    }

    private function contextFor(string $heading): string
    {
        return match (true) {
            (bool) preg_match(self::IGNORED, $heading) => 'ignored',
            (bool) preg_match(self::NICE, $heading) => 'nice',
            (bool) preg_match(self::DUTIES, $heading) => 'duties',
            default => 'must',
        };
    }

    private function kindFor(string $line, ?string $context): RequirementKind
    {
        return match (true) {
            (bool) preg_match(self::NICE, $line), (bool) preg_match(self::ALTERNATIVES, $line) => RequirementKind::NiceToHave,
            (bool) preg_match('/requir|must|essential|minimum|proficien|strong|solid|expert/i', $line) => RequirementKind::MustHave,
            $context === 'must' => RequirementKind::MustHave,
            default => RequirementKind::NiceToHave,
        };
    }

    private function yearsIn(string $line): ?int
    {
        if (! preg_match('/(\d{1,2})\s*\+?\s*(?:-|–|to)?\s*(?:\d{1,2}\s*)?\+?\s*(?:years?|yrs?)/i', $line, $m)) {
            return null;
        }

        $years = (int) $m[1];

        return $years >= 1 && $years <= 15 ? $years : null;
    }
}
