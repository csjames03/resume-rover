<?php

namespace App\Services\Tailoring;

use App\Enums\AiTask;
use App\Enums\RequirementKind;
use App\Enums\SectionType;
use App\Enums\TailoredResumeStatus;
use App\Jobs\GenerateTailoredResume;
use App\Models\JobPosting;
use App\Models\JobRequirement;
use App\Models\ResumeSection;
use App\Models\TailoredResume;
use App\Models\User;
use App\Services\Ai\AiGateway;
use App\Services\Ai\Data\AiRequest;
use DomainException;
use Illuminate\Support\Str;

/**
 * Builds a job-specific draft from the master résumé. The model may reorder,
 * trim and rephrase existing bullets, but must cite the bullet ids it used;
 * ProvenanceGuard then removes or flags anything without evidence.
 */
class ResumeTailor
{
    private const SYSTEM = <<<'PROMPT'
        You tailor résumés honestly. You may reorder, shorten, merge or rephrase the candidate's
        existing bullets to match the job's language. You must not add tools, metrics, employers,
        responsibilities or outcomes that the source bullets do not state. Every bullet you write
        must list the ids of the source bullets it is based on. Respond with a single JSON object only.
        PROMPT;

    public function __construct(
        private readonly AiGateway $gateway,
        private readonly ProvenanceGuard $guard,
    ) {}

    /**
     * Create a pending draft and queue its generation.
     */
    public function request(User $user, JobPosting $posting): TailoredResume
    {
        $resume = $user->activeResume;

        if ($resume === null || ! $resume->isParsed()) {
            throw new DomainException('Upload and review your résumé before tailoring.');
        }

        if (! $this->gateway->isEnabled(AiTask::TailorResume)) {
            throw new DomainException('Tailoring needs an AI provider. Add an API key in your .env file.');
        }

        $version = (int) TailoredResume::query()
            ->where('resume_id', $resume->id)
            ->where('job_posting_id', $posting->id)
            ->max('version') + 1;

        $tailored = $user->tailoredResumes()->create([
            'resume_id' => $resume->id,
            'job_posting_id' => $posting->id,
            'version' => $version,
            'status' => TailoredResumeStatus::Generating,
        ]);

        GenerateTailoredResume::dispatch($tailored->id);

        return $tailored;
    }

    public function generate(TailoredResume $tailored): void
    {
        $tailored->loadMissing('resume.sections.bullets', 'resume.skills', 'posting.requirements.skill');

        $result = $this->gateway->json(
            new AiRequest(AiTask::TailorResume, self::SYSTEM, $this->prompt($tailored), temperature: 0.3),
            [
                'summary' => ['nullable', 'string', 'max:1500'],
                'sections' => ['required', 'array'],
                'sections.*.section_id' => ['required', 'integer'],
                'sections.*.bullets' => ['nullable', 'array'],
                'sections.*.bullets.*.text' => ['required', 'string', 'max:600'],
                'sections.*.bullets.*.source_bullet_ids' => ['nullable', 'array'],
                'sections.*.bullets.*.source_bullet_ids.*' => ['integer'],
                'skills' => ['nullable', 'array'],
                'skills.*' => ['string', 'max:80'],
            ],
        );

        /** @var array{summary?: string|null, sections: list<array{section_id: int, bullets?: list<array{text: string, source_bullet_ids?: list<int>}>}>, skills?: list<string>} $draft */
        $draft = $result->data;
        $review = $this->guard->review($draft, $tailored->resume);

        $tailored->update([
            'status' => TailoredResumeStatus::Draft,
            'content' => $review['content'],
            'flagged_claims' => $review['flagged'],
            'provider' => "{$result->provider} ({$result->model})",
            'error' => null,
        ]);
    }

    /**
     * Apply the user's wording edits. Edited text is the user's own claim, so
     * its automatic flags are cleared; evidence links are kept.
     *
     * @param  array<int, array{bullets?: array<int, string|null>}>  $sections
     */
    public function applyEdits(TailoredResume $tailored, ?string $summary, array $sections): void
    {
        $content = $tailored->content;

        if ($content === null || ! $tailored->isEditable()) {
            throw new DomainException('Only drafts can be edited.');
        }

        if (trim((string) $summary) !== trim((string) $content['summary'])) {
            $content['summary'] = trim((string) $summary) ?: null;
            $content['summary_flags'] = [];
        }

        foreach ($content['sections'] as $s => $section) {
            foreach ($section['bullets'] as $b => $bullet) {
                $edited = trim((string) ($sections[$s]['bullets'][$b] ?? $bullet['text']));

                if ($edited !== $bullet['text']) {
                    $content['sections'][$s]['bullets'][$b]['text'] = $edited;
                    $content['sections'][$s]['bullets'][$b]['flags'] = [];
                }
            }

            $content['sections'][$s]['bullets'] = array_values(array_filter(
                $content['sections'][$s]['bullets'],
                fn (array $bullet) => $bullet['text'] !== '',
            ));
        }

        $tailored->update(['content' => $content]);
    }

    /**
     * The explicit human sign-off the plan requires before a draft is used.
     */
    public function approve(TailoredResume $tailored): void
    {
        if (! $tailored->isEditable()) {
            throw new DomainException('Only drafts can be approved.');
        }

        $tailored->update(['status' => TailoredResumeStatus::Approved, 'approved_at' => now()]);
    }

    private function prompt(TailoredResume $tailored): string
    {
        $posting = $tailored->posting;
        $resume = $tailored->resume;

        $sections = $resume->sections
            ->filter(fn (ResumeSection $s) => in_array($s->type, [SectionType::Experience, SectionType::Project, SectionType::Education, SectionType::Certification], true))
            ->map(fn (ResumeSection $s) => [
                'section_id' => $s->id,
                'type' => $s->type->value,
                'title' => $s->title,
                'organization' => $s->organization,
                'bullets' => $s->bullets->map(fn ($b) => ['id' => $b->id, 'text' => $b->text])->values()->all(),
            ])
            ->values()
            ->all();

        $summary = $resume->sections->firstWhere('type', SectionType::Summary)->description ?? '';
        $requirements = fn (RequirementKind $kind) => $posting->requirements
            ->where('kind', $kind)
            ->map(fn (JobRequirement $r) => $r->skill->name ?? null)
            ->filter()
            ->implode(', ') ?: 'none detected';

        $sectionsJson = json_encode($sections, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $skills = $resume->skills->pluck('name')->implode(', ');
        $description = Str::limit($posting->description, 5000);

        return <<<PROMPT
            JOB: {$posting->title} at {$posting->company}
            Must-have skills: {$requirements(RequirementKind::MustHave)}
            Nice-to-have skills: {$requirements(RequirementKind::NiceToHave)}
            Description:
            """
            {$description}
            """

            CANDIDATE SUMMARY: {$summary}
            CANDIDATE SKILLS: {$skills}
            CANDIDATE SECTIONS (with bullet ids):
            {$sectionsJson}

            Return:
            {
              "summary": "2-3 sentence professional summary using only facts above, or null",
              "sections": [{"section_id": 12, "bullets": [{"text": "...", "source_bullet_ids": [34]}]}],
              "skills": ["the candidate's skills most relevant to this job, most relevant first"]
            }
            Order sections and bullets by relevance to the job. Keep every experience section.
            You may drop bullets that are irrelevant. Only use section ids and bullet ids from above.
            PROMPT;
    }
}
