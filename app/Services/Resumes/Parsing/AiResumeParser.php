<?php

namespace App\Services\Resumes\Parsing;

use App\Enums\AiTask;
use App\Enums\SectionType;
use App\Services\Ai\AiGateway;
use App\Services\Ai\Data\AiRequest;
use App\Services\Ai\Exceptions\AiProviderException;
use App\Services\Ai\Exceptions\AiUnavailableException;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Uses an LLM to structure résumé text. Handles unusual layouts much better
 * than the rule-based parser, but is told to copy text, never to invent it.
 */
class AiResumeParser implements ResumeParser
{
    private const MAX_INPUT_CHARS = 15000;

    private const SYSTEM = <<<'PROMPT'
        You convert résumé text into JSON. Copy wording exactly as written; never invent,
        summarize, or improve anything. If a field is not present, use null.
        Respond with a single JSON object only.
        PROMPT;

    public function __construct(private readonly AiGateway $gateway) {}

    /**
     * @throws AiUnavailableException|AiProviderException
     */
    public function parse(string $text): ParsedResume
    {
        $types = implode('|', SectionType::values());

        $prompt = <<<PROMPT
            Return this shape:
            {
              "sections": [{
                "type": "{$types}",
                "title": "job title, degree, project or certificate name, or null",
                "organization": "company, school or issuer, or null",
                "location": "string or null",
                "start_date": "YYYY-MM or YYYY or null",
                "end_date": "YYYY-MM or YYYY or null",
                "is_current": true/false,
                "description": "free text that is not a bullet, or null (use for summary and links)",
                "bullets": ["each bullet point, verbatim"]
              }],
              "skills": ["every skill, tool or technology listed"]
            }
            One section per job, project, degree or certificate, in document order.
            Put the professional summary in one "summary" section and each profile URL in a "link" section.

            Résumé text:
            """
            {$this->truncate($text)}
            """
            PROMPT;

        $result = $this->gateway->json(new AiRequest(AiTask::ParseResume, self::SYSTEM, $prompt, temperature: 0.0), [
            'sections' => ['required', 'array'],
            'sections.*.type' => ['required', Rule::enum(SectionType::class)],
            'sections.*.title' => ['nullable', 'string', 'max:255'],
            'sections.*.organization' => ['nullable', 'string', 'max:255'],
            'sections.*.location' => ['nullable', 'string', 'max:255'],
            'sections.*.start_date' => ['nullable', 'string', 'max:20'],
            'sections.*.end_date' => ['nullable', 'string', 'max:20'],
            'sections.*.is_current' => ['nullable', 'boolean'],
            'sections.*.description' => ['nullable', 'string'],
            'sections.*.bullets' => ['nullable', 'array'],
            'sections.*.bullets.*' => ['string'],
            'skills' => ['nullable', 'array'],
            'skills.*' => ['string', 'max:80'],
        ]);

        /** @var list<array<string, mixed>> $rawSections */
        $rawSections = $result->data['sections'];

        $sections = array_map(fn (array $section) => new ParsedSection(
            type: SectionType::from($section['type']),
            title: $this->stringOrNull($section['title'] ?? null),
            organization: $this->stringOrNull($section['organization'] ?? null),
            location: $this->stringOrNull($section['location'] ?? null),
            startDate: DateRange::parseDate($this->stringOrNull($section['start_date'] ?? null)),
            endDate: DateRange::parseDate($this->stringOrNull($section['end_date'] ?? null)),
            isCurrent: (bool) ($section['is_current'] ?? false),
            description: $this->stringOrNull($section['description'] ?? null),
            bullets: array_values(array_filter(array_map('trim', (array) ($section['bullets'] ?? [])))),
            confidence: 0.9,
        ), $rawSections);

        /** @var list<string> $skills */
        $skills = $result->data['skills'] ?? [];

        return new ParsedResume($sections, $skills, "ai:{$result->provider}");
    }

    private function truncate(string $text): string
    {
        return Str::limit($text, self::MAX_INPUT_CHARS, '');
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
