<?php

namespace App\Services\Email;

use App\Enums\AiTask;
use App\Enums\EmailStatus;
use App\Enums\SectionType;
use App\Models\ApplicationEmail;
use App\Models\JobPosting;
use App\Models\Resume;
use App\Models\ResumeSection;
use App\Models\User;
use App\Services\Ai\AiAvailability;
use App\Services\Ai\AiGateway;
use App\Services\Ai\Data\AiRequest;
use App\Services\Ai\Exceptions\AiProviderException;
use App\Services\Ai\Exceptions\AiUnavailableException;
use App\Services\Resumes\ClaimChecker;
use DomainException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Writes the first draft of an application email. AI when available, a
 * plain template otherwise; either way, claims are checked against the
 * résumé before the user sees it.
 */
class ApplicationEmailDrafter
{
    private const SYSTEM = <<<'PROMPT'
        You write short, specific job application emails in plain text.
        Use only facts given about the candidate; never invent experience, numbers or skills.
        No placeholders like [Hiring Manager]; if no name is known, greet "Hello". No markdown.
        Respond with a single JSON object only.
        PROMPT;

    /** Addresses in a posting that are clearly not where applications go. */
    private const NOT_FOR_APPLICATIONS = '/no-?reply|privacy|accommodation|security|legal|abuse|support|unsubscribe/i';

    public function __construct(
        private readonly AiGateway $gateway,
        private readonly AiAvailability $availability,
        private readonly ClaimChecker $claims,
    ) {}

    public function draft(User $user, JobPosting $posting): ApplicationEmail
    {
        $user->loadMissing('activeResume');
        $resume = $user->activeResume;

        if ($resume === null || ! $resume->isParsed()) {
            throw new DomainException('Upload and review your résumé before drafting an application email.');
        }

        [$subject, $body, $draftedBy] = $this->compose($user, $resume, $posting);

        return $user->applicationEmails()->create([
            'job_posting_id' => $posting->id,
            'resume_id' => $resume->id,
            'to' => $this->recipientFrom($posting->description),
            'subject' => $subject,
            'body' => $body,
            'attach_resume' => true,
            'flags' => $this->flagsFor($body, $resume),
            'status' => EmailStatus::Draft,
            'drafted_by' => $draftedBy,
        ]);
    }

    /**
     * Claims in the body that the résumé doesn't support.
     *
     * @return list<string>
     */
    public function flagsFor(string $body, Resume $resume): array
    {
        return $this->claims->unsupported($body, $this->claims->resumeText($resume), $this->claims->skillIds($resume));
    }

    /**
     * The first address in the posting that looks like it accepts applications.
     */
    public function recipientFrom(string $text): ?string
    {
        preg_match_all('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $text, $matches);

        foreach ($matches[0] as $address) {
            if (! preg_match(self::NOT_FOR_APPLICATIONS, $address)) {
                return Str::lower(rtrim($address, '.'));
            }
        }

        return null;
    }

    /**
     * @return array{string, string, string} subject, body, drafted by
     */
    private function compose(User $user, Resume $resume, JobPosting $posting): array
    {
        // Over the daily allowance or rate-limited: the template still gives a usable draft.
        if ($this->availability->tryClaim($user, AiTask::DraftEmail)) {
            try {
                $result = $this->gateway->json(
                    new AiRequest(AiTask::DraftEmail, self::SYSTEM, $this->prompt($user, $resume, $posting), temperature: 0.5),
                    ['subject' => ['required', 'string', 'max:200'], 'body' => ['required', 'string', 'max:4000']],
                );

                return [trim($result->data['subject']), trim($result->data['body']), "{$result->provider} ({$result->model})"];
            } catch (AiUnavailableException|AiProviderException $e) {
                Log::info('AI email draft unavailable, using the template', ['reason' => $e->getMessage()]);
            }
        }

        return [...$this->template($user, $resume, $posting), 'template'];
    }

    private function prompt(User $user, Resume $resume, JobPosting $posting): string
    {
        $resume->loadMissing('sections', 'skills');
        $match = $user->jobMatches()->where('job_posting_id', $posting->id)->first();

        $experience = $resume->sections
            ->filter(fn (ResumeSection $s) => $s->type === SectionType::Experience)
            ->map(fn (ResumeSection $s) => trim("{$s->title} at {$s->organization}"))
            ->take(4)
            ->implode('; ');
        $summary = $resume->sections->firstWhere('type', SectionType::Summary)->description ?? '';
        $strengths = collect($match->strengths ?? [])->pluck('label')->take(8)->implode(', ') ?: 'unknown';
        $description = Str::limit($posting->description, 2500);

        return <<<PROMPT
            CANDIDATE: {$user->name}
            Summary: {$summary}
            Recent roles: {$experience}
            Skills: {$resume->skills->pluck('name')->implode(', ')}
            Strongest overlap with this job: {$strengths}

            JOB: {$posting->title} at {$posting->company}
            """
            {$description}
            """

            Return {"subject": "...", "body": "..."}.
            Body: under 180 words, 3 short paragraphs, mention the attached résumé, sign off with the candidate's name.
            PROMPT;
    }

    /**
     * @return array{string, string}
     */
    private function template(User $user, Resume $resume, JobPosting $posting): array
    {
        $resume->loadMissing('skills');
        $match = $user->jobMatches()->where('job_posting_id', $posting->id)->first();
        $skills = collect($match->strengths ?? [])->where('type', 'skill')->pluck('label')->take(4)->values();
        $skillLine = $skills->isEmpty() ? '' : ' My experience with '.$skills->join(', ', ' and ').' lines up well with what you\'re looking for.';

        $body = <<<BODY
            Hello,

            I'd like to apply for the {$posting->title} role at {$posting->company}.{$skillLine}

            I've attached my résumé with more detail. I'd welcome the chance to talk about how I can help your team.

            Thank you for your time,
            {$user->name}
            BODY;

        return ["Application: {$posting->title} – {$user->name}", $body];
    }
}
