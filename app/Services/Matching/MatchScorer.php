<?php

namespace App\Services\Matching;

use App\Enums\MatchCategory;
use App\Enums\RemotePreference;
use App\Enums\RemoteType;
use App\Enums\RequirementKind;
use App\Enums\Seniority;
use App\Models\JobPosting;
use App\Models\JobRequirement;
use App\Services\Matching\Data\MatchResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Pure, deterministic scoring: the same profile and posting always give the
 * same score, and every point gained or lost shows up as a strength or gap.
 *
 * @phpstan-import-type MatchPoint from MatchResult
 */
class MatchScorer
{
    /** Fewer recognizable requirements than this means we know too little to call it a strong fit. */
    private const MIN_EVIDENCE = 3;

    private const LOW_EVIDENCE_CAP = 70;

    private const EXCLUDED_CAP = 20;

    /** @var list<MatchPoint> */
    private array $strengths = [];

    /** @var list<MatchPoint> */
    private array $gaps = [];

    /**
     * The posting must have its requirements (with skills) loaded.
     */
    public function score(CandidateProfile $candidate, JobPosting $posting): MatchResult
    {
        $this->strengths = [];
        $this->gaps = [];

        /** @var Collection<int, JobRequirement> $requirements */
        $requirements = $posting->requirements;
        $skillRequirements = $requirements->whereNotNull('skill_id');

        $components = array_filter([
            'must_have' => $this->skillCoverage($candidate, $skillRequirements->where('kind', RequirementKind::MustHave)),
            'nice_to_have' => $this->skillCoverage($candidate, $skillRequirements->where('kind', RequirementKind::NiceToHave)),
            'experience' => $this->experienceFit($candidate, $requirements->whereNotNull('min_years')->first()),
            'title' => $this->titleFit($candidate, $posting->title),
        ], fn (?float $value) => $value !== null);

        $score = $this->weightedScore($components);

        if ($skillRequirements->count() < self::MIN_EVIDENCE) {
            $score = min($score, self::LOW_EVIDENCE_CAP);
            $this->gap('coverage', 'Few recognizable requirements in this posting; read it yourself before deciding');
        }

        // Matching skills alone don't make a finance role a fit for a developer.
        if (($components['title'] ?? null) === 0.0) {
            $score = min($score, (int) config('matching.bands.strong') - 1);
            $this->gap('title', "Title doesn't resemble your past or target roles");
        }

        $score -= $this->preferencePenalties($candidate, $posting);

        if ($this->hasExcludedKeyword($candidate, $posting)) {
            $score = min($score, self::EXCLUDED_CAP);
        }

        $score = (int) max(0, min(100, round($score)));

        return new MatchResult($score, MatchCategory::fromScore($score), $this->strengths, $this->gaps);
    }

    /**
     * Share of the given skill requirements the candidate meets, or null if there are none.
     *
     * @param  Collection<int, JobRequirement>  $requirements
     */
    private function skillCoverage(CandidateProfile $candidate, Collection $requirements): ?float
    {
        if ($requirements->isEmpty()) {
            return null;
        }

        $met = 0;

        foreach ($requirements as $requirement) {
            $name = $requirement->skill->name ?? 'Unknown skill';
            $kind = $requirement->kind->value;

            if ($candidate->hasSkill((int) $requirement->skill_id)) {
                $met++;
                $this->strength('skill', $name, $requirement->raw_text, $kind);
            } else {
                $this->gap('skill', $name, $requirement->raw_text, $kind);
            }
        }

        return $met / $requirements->count();
    }

    private function experienceFit(CandidateProfile $candidate, ?JobRequirement $requirement): ?float
    {
        if ($requirement === null || $requirement->min_years === null) {
            return null;
        }

        $required = $requirement->min_years;
        $have = $candidate->yearsOfExperience;
        $label = "Asks for {$required}+ years; your résumé shows about {$have}";

        if ($have >= $required) {
            $this->strength('experience', $label, $requirement->raw_text);

            return 1.0;
        }

        $this->gap('experience', $label, $requirement->raw_text, $requirement->kind->value);

        return $have / $required;
    }

    /**
     * Word overlap between the posting title and the candidate's past or target titles.
     */
    private function titleFit(CandidateProfile $candidate, string $postingTitle): ?float
    {
        if ($candidate->titles === []) {
            return null;
        }

        $postingWords = $this->titleWords($postingTitle);

        if ($postingWords === []) {
            return null;
        }

        $best = 0.0;
        $bestTitle = null;

        foreach ($candidate->titles as $title) {
            $overlap = count(array_intersect($postingWords, $this->titleWords($title))) / count($postingWords);

            if ($overlap > $best) {
                [$best, $bestTitle] = [$overlap, $title];
            }
        }

        if ($best >= 0.5 && $bestTitle !== null) {
            $this->strength('title', 'Title lines up with "'.Str::title($bestTitle).'"');
        }

        return $best;
    }

    /**
     * @param  array<string, float>  $components  component => 0..1
     */
    private function weightedScore(array $components): float
    {
        if ($components === []) {
            return 0;
        }

        $weights = config('matching.weights');
        $totalWeight = 0;
        $earned = 0.0;

        foreach ($components as $name => $value) {
            $totalWeight += $weights[$name];
            $earned += $weights[$name] * $value;
        }

        return $earned / $totalWeight * 100;
    }

    private function preferencePenalties(CandidateProfile $candidate, JobPosting $posting): float
    {
        $penalties = config('matching.penalties');
        $total = 0.0;

        if ($candidate->remotePreference === RemotePreference::Remote && $posting->remote_type === RemoteType::Onsite) {
            $total += $penalties['remote_mismatch'];
            $this->gap('remote', 'On-site role; you prefer remote');
        } elseif ($candidate->remotePreference === RemotePreference::Remote && $posting->remote_type === RemoteType::Hybrid) {
            $total += $penalties['remote_mismatch'] / 2;
            $this->gap('remote', 'Hybrid role; you prefer remote');
        } elseif ($posting->remote_type === RemoteType::Remote && $candidate->remotePreference !== RemotePreference::Onsite) {
            $this->strength('remote', 'Remote role');
        }

        if ($candidate->locations !== [] && $posting->remote_type !== RemoteType::Remote
            && ! Str::contains(Str::lower((string) $posting->location), $candidate->locations)) {
            $total += $penalties['location_mismatch'];
            $this->gap('location', 'Location "'.($posting->location ?? 'unspecified').'" is not in your preferred list');
        }

        $isSeniorRole = Str::contains(Str::lower($posting->title), config('matching.senior_title_keywords'));
        $isEarlyCareer = in_array($candidate->seniority, [Seniority::Intern, Seniority::Junior], true);

        if ($isSeniorRole && $isEarlyCareer) {
            $total += $penalties['seniority_mismatch'];
            $this->gap('seniority', 'Senior-level title; your target level is '.$candidate->seniority->label());
        }

        return $total;
    }

    private function hasExcludedKeyword(CandidateProfile $candidate, JobPosting $posting): bool
    {
        $haystack = Str::lower($posting->title.' '.$posting->company);

        foreach ($candidate->excludedKeywords as $keyword) {
            if ($keyword !== '' && Str::contains($haystack, $keyword)) {
                $this->gap('excluded', "Contains your excluded keyword \"{$keyword}\"");

                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function titleWords(string $title): array
    {
        $noise = ['senior', 'sr', 'junior', 'jr', 'lead', 'staff', 'principal', 'i', 'ii', 'iii', 'iv', 'the', 'and', 'of', 'for', 'a', 'an', 'to', 'in', 'at', 'remote', 'hybrid', 'with'];
        $words = preg_split('/[^\p{L}\p{N}+#]+/u', Str::lower($title)) ?: [];

        return array_values(array_unique(array_diff(array_filter($words), $noise)));
    }

    private function strength(string $type, string $label, ?string $detail = null, ?string $kind = null): void
    {
        $this->strengths[] = array_filter(compact('type', 'label', 'detail', 'kind'), fn ($v) => $v !== null);
    }

    private function gap(string $type, string $label, ?string $detail = null, ?string $kind = null): void
    {
        $this->gaps[] = array_filter(compact('type', 'label', 'detail', 'kind'), fn ($v) => $v !== null);
    }
}
