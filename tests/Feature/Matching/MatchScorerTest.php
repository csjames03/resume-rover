<?php

namespace Tests\Feature\Matching;

use App\Enums\MatchCategory;
use App\Enums\RemotePreference;
use App\Enums\RemoteType;
use App\Enums\RequirementKind;
use App\Enums\Seniority;
use App\Models\JobPosting;
use App\Models\JobRequirement;
use App\Models\Skill;
use App\Services\Matching\CandidateProfile;
use App\Services\Matching\MatchScorer;
use Tests\TestCase;

/**
 * The scorer is pure, so these tests build models in memory: no database.
 */
class MatchScorerTest extends TestCase
{
    public function test_meeting_every_requirement_is_a_strong_fit(): void
    {
        $posting = $this->posting('Backend Developer', [
            [1, RequirementKind::MustHave], [2, RequirementKind::MustHave], [3, RequirementKind::NiceToHave],
        ], minYears: 3);

        $result = (new MatchScorer)->score($this->candidate(skills: [1, 2, 3], years: 5), $posting);

        $this->assertSame(100, $result->score);
        $this->assertSame(MatchCategory::Strong, $result->category);
        $this->assertSame([], $result->gaps);
    }

    public function test_missing_must_haves_matter_more_than_nice_to_haves(): void
    {
        $requirements = [[1, RequirementKind::MustHave], [2, RequirementKind::MustHave], [3, RequirementKind::NiceToHave], [4, RequirementKind::NiceToHave]];
        $scorer = new MatchScorer;

        $missingMust = $scorer->score($this->candidate(skills: [1, 3, 4]), $this->posting('Developer', $requirements));
        $missingNice = $scorer->score($this->candidate(skills: [1, 2, 3]), $this->posting('Developer', $requirements));

        $this->assertGreaterThan($missingMust->score, $missingNice->score);
        $this->assertContains('Skill 2', array_column($missingMust->gaps, 'label'));
    }

    public function test_preference_mismatches_lower_the_score_and_explain_why(): void
    {
        $posting = $this->posting('Senior Backend Developer', [[1, RequirementKind::MustHave], [2, RequirementKind::MustHave], [3, RequirementKind::MustHave]], remote: RemoteType::Onsite);

        $flexible = (new MatchScorer)->score($this->candidate(skills: [1, 2, 3]), $posting);
        $picky = (new MatchScorer)->score($this->candidate(skills: [1, 2, 3], remote: RemotePreference::Remote, seniority: Seniority::Junior), $posting);

        $this->assertSame(35, $flexible->score - $picky->score);
        $this->assertEqualsCanonicalizing(['remote', 'seniority'], array_column($picky->gaps, 'type'));
    }

    public function test_excluded_keywords_force_a_low_match(): void
    {
        $posting = $this->posting('Sales Engineer', [[1, RequirementKind::MustHave], [2, RequirementKind::MustHave]]);

        $result = (new MatchScorer)->score($this->candidate(skills: [1, 2], excluded: ['sales']), $posting);

        $this->assertSame(MatchCategory::Low, $result->category);
    }

    public function test_an_unrelated_title_cannot_be_a_strong_fit(): void
    {
        $posting = $this->posting('Director, FP&A', [[1, RequirementKind::MustHave], [2, RequirementKind::MustHave], [3, RequirementKind::NiceToHave]]);

        $result = (new MatchScorer)->score($this->candidate(skills: [1, 2, 3]), $posting);

        $this->assertSame(MatchCategory::Relevant, $result->category);
        $this->assertContains('title', array_column($result->gaps, 'type'));
    }

    public function test_postings_with_little_evidence_are_capped(): void
    {
        $posting = $this->posting('Backend Developer', [[1, RequirementKind::MustHave]]);

        $result = (new MatchScorer)->score($this->candidate(skills: [1]), $posting);

        $this->assertSame(70, $result->score);
        $this->assertContains('coverage', array_column($result->gaps, 'type'));
    }

    /**
     * @param  list<int>  $skills
     * @param  list<string>  $excluded
     */
    private function candidate(
        array $skills,
        float $years = 2,
        RemotePreference $remote = RemotePreference::Any,
        ?Seniority $seniority = null,
        array $excluded = [],
    ): CandidateProfile {
        return new CandidateProfile(
            resumeId: 1,
            profileVersion: 1,
            skills: array_combine($skills, array_map(fn (int $id) => "Skill {$id}", $skills)),
            yearsOfExperience: $years,
            titles: ['backend developer'],
            remotePreference: $remote,
            seniority: $seniority,
            locations: [],
            excludedKeywords: $excluded,
        );
    }

    /**
     * @param  list<array{int, RequirementKind}>  $skillRequirements
     */
    private function posting(string $title, array $skillRequirements, ?int $minYears = null, RemoteType $remote = RemoteType::Remote): JobPosting
    {
        $requirements = collect($skillRequirements)->map(function (array $pair) {
            [$skillId, $kind] = $pair;
            $requirement = new JobRequirement(['skill_id' => $skillId, 'kind' => $kind, 'raw_text' => "Uses skill {$skillId}"]);

            return $requirement->setRelation('skill', new Skill(['name' => "Skill {$skillId}"]));
        });

        if ($minYears !== null) {
            $requirements->push(new JobRequirement(['kind' => RequirementKind::MustHave, 'min_years' => $minYears, 'raw_text' => "{$minYears}+ years"]));
        }

        $posting = new JobPosting(['title' => $title, 'company' => 'Acme', 'remote_type' => $remote]);

        return $posting->setRelation('requirements', $requirements);
    }
}
