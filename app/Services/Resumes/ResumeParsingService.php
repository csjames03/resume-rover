<?php

namespace App\Services\Resumes;

use App\Enums\AiTask;
use App\Enums\ResumeParseStatus;
use App\Enums\SkillSource;
use App\Jobs\ScoreMatches;
use App\Models\Resume;
use App\Services\Ai\AiGateway;
use App\Services\Ai\Exceptions\AiProviderException;
use App\Services\Ai\Exceptions\AiUnavailableException;
use App\Services\Resumes\Parsing\AiResumeParser;
use App\Services\Resumes\Parsing\HeuristicResumeParser;
use App\Services\Resumes\Parsing\ParsedResume;
use App\Services\Skills\SkillMatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Extract → parse → store. Prefers the AI parser when a provider is configured
 * and quietly falls back to the rule-based parser when AI is unavailable.
 */
class ResumeParsingService
{
    public function __construct(
        private readonly ResumeTextExtractor $extractor,
        private readonly HeuristicResumeParser $heuristicParser,
        private readonly AiResumeParser $aiParser,
        private readonly AiGateway $gateway,
        private readonly SkillMatcher $skills,
    ) {}

    public function parse(Resume $resume): void
    {
        $resume->update(['parse_status' => ResumeParseStatus::Processing, 'parse_error' => null]);

        try {
            $text = $this->extractor->extract(
                Storage::disk(ResumeUploader::DISK)->path($resume->storage_path),
                $resume->mime_type,
            );

            if (mb_strlen($text) < 50) {
                throw new ResumeParsingFailed('No readable text was found. Scanned/image-only PDFs are not supported yet.');
            }

            $this->store($resume, $text, $this->parseText($text));
        } catch (Throwable $e) {
            $resume->update([
                'parse_status' => ResumeParseStatus::Failed,
                'parse_error' => $e instanceof ResumeParsingFailed ? $e->getMessage() : 'The file could not be read.',
            ]);

            if (! $e instanceof ResumeParsingFailed) {
                Log::warning('Résumé parsing failed', ['resume_id' => $resume->id, 'error' => $e->getMessage()]);
            }

            return;
        }

        ScoreMatches::dispatch($resume->user_id);
    }

    private function parseText(string $text): ParsedResume
    {
        if ($this->gateway->isEnabled(AiTask::ParseResume)) {
            try {
                return $this->aiParser->parse($text);
            } catch (AiUnavailableException|AiProviderException $e) {
                Log::info('AI résumé parsing unavailable, using heuristic parser', ['reason' => $e->getMessage()]);
            }
        }

        return $this->heuristicParser->parse($text);
    }

    private function store(Resume $resume, string $text, ParsedResume $parsed): void
    {
        DB::transaction(function () use ($resume, $text, $parsed) {
            $resume->sections()->delete();

            foreach ($parsed->sections as $position => $section) {
                $model = $resume->sections()->create([
                    'type' => $section->type,
                    'position' => $position,
                    'title' => $this->clip($section->title),
                    'organization' => $this->clip($section->organization),
                    'location' => $this->clip($section->location),
                    'start_date' => $section->startDate,
                    'end_date' => $section->endDate,
                    'is_current' => $section->isCurrent,
                    'description' => $section->description,
                    'source_text' => $section->sourceText,
                    'confidence' => $section->confidence,
                ]);

                foreach ($section->bullets as $index => $bullet) {
                    $model->bullets()->create(['position' => $index, 'text' => $bullet]);
                }
            }

            $this->syncParsedSkills($resume, $text, $parsed->skillTerms);

            $resume->update([
                'raw_text' => $text,
                'parse_status' => ResumeParseStatus::Parsed,
                'parser' => $parsed->parser,
                'parsed_at' => now(),
                'profile_version' => $resume->profile_version + 1,
            ]);
        });
    }

    /**
     * Replace previously parsed skills; skills the user added by hand are kept.
     *
     * @param  list<string>  $terms
     */
    private function syncParsedSkills(Resume $resume, string $text, array $terms): void
    {
        $skillIds = array_keys($this->skills->find($text));

        foreach ($terms as $term) {
            $skillIds[] = $this->skills->resolve($term);
        }

        $skillIds = array_unique(array_filter($skillIds));

        $resume->skills()->wherePivot('source', SkillSource::Parsed->value)->detach();
        $manualIds = $resume->skills()->pluck('skills.id')->all();

        $resume->skills()->attach(
            array_fill_keys(array_diff($skillIds, $manualIds), ['source' => SkillSource::Parsed->value]),
        );
    }

    private function clip(?string $value): ?string
    {
        return $value === null ? null : Str::limit($value, 250, '');
    }
}
