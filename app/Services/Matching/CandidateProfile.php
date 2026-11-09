<?php

namespace App\Services\Matching;

use App\Enums\RemotePreference;
use App\Enums\SectionType;
use App\Enums\Seniority;
use App\Models\Resume;
use App\Models\ResumeSection;
use App\Models\UserPreference;
use Carbon\CarbonImmutable;

/**
 * Everything the scorer needs to know about the candidate, computed once per
 * scoring run instead of once per posting.
 */
final readonly class CandidateProfile
{
    /**
     * @param  array<int, string>  $skills  skill id => name
     * @param  list<string>  $titles  past job titles and target titles, lowercase
     * @param  list<string>  $locations  preferred locations, lowercase
     * @param  list<string>  $excludedKeywords  lowercase
     */
    public function __construct(
        public int $resumeId,
        public int $profileVersion,
        public array $skills,
        public float $yearsOfExperience,
        public array $titles,
        public RemotePreference $remotePreference,
        public ?Seniority $seniority,
        public array $locations,
        public array $excludedKeywords,
    ) {}

    public static function fromResume(Resume $resume, UserPreference $preference): self
    {
        $resume->loadMissing('skills', 'sections');

        $experience = $resume->sections->filter(fn (ResumeSection $s) => $s->type === SectionType::Experience);
        $lower = fn (?array $values) => array_values(array_filter(array_map(fn ($v) => mb_strtolower(trim((string) $v)), $values ?? [])));

        return new self(
            resumeId: $resume->id,
            profileVersion: $resume->profile_version,
            skills: $resume->skills->pluck('name', 'id')->all(),
            yearsOfExperience: self::yearsFrom($experience->values()->all()),
            titles: array_values(array_unique([
                ...$lower($experience->pluck('title')->all()),
                ...$lower($preference->target_titles),
            ])),
            remotePreference: $preference->remote_preference,
            seniority: $preference->seniority,
            locations: $lower($preference->locations),
            excludedKeywords: $lower($preference->excluded_keywords),
        );
    }

    public function hasSkill(int $skillId): bool
    {
        return isset($this->skills[$skillId]);
    }

    /**
     * Total experience with overlapping jobs counted once.
     *
     * @param  array<int, ResumeSection>  $sections
     */
    private static function yearsFrom(array $sections): float
    {
        $ranges = [];

        foreach ($sections as $section) {
            if ($section->start_date === null) {
                continue;
            }

            $start = CarbonImmutable::parse($section->start_date);
            $end = $section->is_current || $section->end_date === null ? CarbonImmutable::now() : CarbonImmutable::parse($section->end_date);

            if ($end->greaterThan($start)) {
                $ranges[] = [$start, $end];
            }
        }

        usort($ranges, fn (array $a, array $b) => $a[0] <=> $b[0]);

        $months = 0.0;
        $cursor = null;

        foreach ($ranges as [$start, $end]) {
            if ($cursor !== null && $start->lessThan($cursor)) {
                $start = $cursor;
            }

            if ($end->greaterThan($start)) {
                $months += $start->diffInMonths($end);
                $cursor = $end;
            }
        }

        return round($months / 12, 1);
    }
}
