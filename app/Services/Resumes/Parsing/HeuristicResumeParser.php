<?php

namespace App\Services\Resumes\Parsing;

use App\Enums\SectionType;
use Illuminate\Support\Str;

/**
 * Rule-based parser that needs no AI. It finds section headings, splits
 * entries on date ranges and bullets, and leaves the rest to the review
 * screen. Accuracy is "good first draft", which is why the user can edit
 * everything afterwards.
 */
class HeuristicResumeParser implements ResumeParser
{
    public const NAME = 'heuristic';

    private const BULLET = '/^\s*(?:[•●▪◦‣∙·○■□➢►✓✔\-–*]|\d{1,2}[.)])\s*/u';

    /**
     * Heading phrases per bucket. "header" is everything before the first heading.
     */
    private const HEADINGS = [
        'summary' => ['summary', 'professional summary', 'career summary', 'profile', 'professional profile', 'about me', 'about', 'objective', 'career objective'],
        'experience' => ['experience', 'work experience', 'professional experience', 'relevant experience', 'employment', 'employment history', 'work history', 'career history', 'internship', 'internships', 'internship experience'],
        'project' => ['projects', 'personal projects', 'academic projects', 'side projects', 'key projects', 'selected projects', 'project experience'],
        'education' => ['education', 'academic background', 'educational background', 'academic history', 'education and training'],
        'certification' => ['certifications', 'certification', 'certificates', 'licenses', 'licenses and certifications', 'licenses & certifications', 'courses', 'training', 'trainings', 'seminars and trainings'],
        'skills' => ['skills', 'technical skills', 'key skills', 'core competencies', 'competencies', 'technologies', 'tech stack', 'tools', 'skills and tools', 'skills & tools', 'technical expertise'],
        'other' => ['awards', 'honors', 'awards and honors', 'achievements', 'volunteer', 'volunteering', 'volunteer experience', 'leadership', 'activities', 'extracurricular activities', 'interests', 'languages', 'references', 'publications', 'organizations', 'affiliations'],
    ];

    public function parse(string $text): ParsedResume
    {
        $sections = $this->linkSections($text);
        $skillTerms = [];
        $blocks = $this->splitIntoBlocks($this->lines($text));
        $foundHeadings = count($blocks) > 1;

        foreach ($blocks as $block) {
            if ($block['bucket'] === 'skills') {
                array_push($skillTerms, ...$this->skillTerms($block['lines']));

                continue;
            }

            array_push($sections, ...$this->sectionsFor($block, $foundHeadings));
        }

        return new ParsedResume($sections, array_values(array_unique($skillTerms)), self::NAME);
    }

    /**
     * @param  array{bucket: string, heading: string|null, lines: list<string>}  $block
     * @return list<ParsedSection>
     */
    private function sectionsFor(array $block, bool $foundHeadings): array
    {
        $lines = $block['lines'];

        if ($lines === []) {
            return [];
        }

        return match ($block['bucket']) {
            // Name and contact details; only useful when the file has no headings at all.
            'header' => $foundHeadings ? [] : [new ParsedSection(SectionType::Other, 'Résumé text', description: implode("\n", $lines), confidence: 0.2)],
            'summary' => [new ParsedSection(SectionType::Summary, description: implode(' ', $lines), sourceText: implode("\n", $lines), confidence: 0.8)],
            'other' => [$this->freeformSection((string) $block['heading'], $lines)],
            default => $this->entrySections(SectionType::from($block['bucket']), $lines),
        };
    }

    /**
     * @return list<string>
     */
    private function lines(string $text): array
    {
        $raw = preg_split('/\R/u', str_replace("\u{00A0}", ' ', $text)) ?: [];
        $lines = [];

        foreach ($raw as $line) {
            $line = trim((string) preg_replace('/[ \t]+/u', ' ', $line));

            // Blank lines and page counters such as "1/2" or "Page 2 of 3" carry no content.
            if ($line === '' || preg_match('/^(?:page\s*)?\d+\s*(?:\/|of)\s*\d+$/i', $line)) {
                continue;
            }

            $previous = end($lines);

            // A wrapped range end ("2025", "Current ○ Built…") completes the previous line's dates.
            if ($previous !== false && preg_match('/^((?:19|20)\d{2}|present|current|now|ongoing)\b\s*(.*)$/iu', $line, $m)) {
                $completed = DateRange::completeDangling($previous, $m[1]);

                if ($completed !== null) {
                    $lines[array_key_last($lines)] = $completed;

                    if (trim($m[2]) !== '') {
                        $lines[] = trim($m[2]);
                    }

                    continue;
                }
            }

            // PDF extraction often puts a bullet glyph alone on its own line.
            if ($previous !== false && preg_match('/^[•●▪◦‣∙·○■□➢►✓✔*]$/u', $previous)) {
                $lines[array_key_last($lines)] = $previous.' '.$line;

                continue;
            }

            $lines[] = $line;
        }

        return $lines;
    }

    /**
     * @param  list<string>  $lines
     * @return list<array{bucket: string, heading: string|null, lines: list<string>}>
     */
    private function splitIntoBlocks(array $lines): array
    {
        $blocks = [];
        $current = ['bucket' => 'header', 'heading' => null, 'lines' => []];

        foreach ($lines as $line) {
            $bucket = $this->headingBucket($line);

            if ($bucket === null) {
                $current['lines'][] = $line;

                continue;
            }

            $blocks[] = $current;
            $current = ['bucket' => $bucket, 'heading' => $line, 'lines' => []];
        }

        $blocks[] = $current;

        return $blocks;
    }

    private function headingBucket(string $line): ?string
    {
        if (mb_strlen($line) > 45 || preg_match(self::BULLET, $line) || preg_match('/\d{4}/', $line)) {
            return null;
        }

        $normalized = trim((string) preg_replace('/[^\p{L}&\s]+/u', ' ', mb_strtolower($line)));
        $normalized = (string) preg_replace('/\s+/', ' ', $normalized);

        foreach (self::HEADINGS as $bucket => $phrases) {
            if (in_array($normalized, $phrases, true)) {
                return $bucket;
            }
        }

        // ALL-CAPS lines such as "WORK EXPERIENCE & INTERNSHIPS" are headings even if not exact.
        $isUppercase = mb_strtoupper($line) === $line && preg_match('/\p{L}/u', $line);

        if ($isUppercase && str_word_count($normalized) <= 5) {
            foreach (self::HEADINGS as $bucket => $phrases) {
                foreach ($phrases as $phrase) {
                    if (str_starts_with($normalized, $phrase)) {
                        return $bucket;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Split an experience/project/education block into entries. A new entry
     * starts at a non-bullet line after bullets, or at a second date range.
     *
     * @param  list<string>  $lines
     * @return list<ParsedSection>
     */
    private function entrySections(SectionType $type, array $lines): array
    {
        $entries = [];
        $current = null;

        foreach ($lines as $line) {
            if ($this->isBullet($line)) {
                $current ??= $this->newEntry();
                $current['bullets'][] = $this->stripBullet($line);
                $current['source'][] = $line;

                continue;
            }

            $lastBullet = $current === null ? false : end($current['bullets']);

            if ($current !== null && $lastBullet !== false && $this->continuesBullet($line, $lastBullet)) {
                $current['bullets'][count($current['bullets']) - 1] .= ' '.$line;
                $current['source'][] = $line;

                continue;
            }

            $range = DateRange::find($line);

            if ($current === null || $current['bullets'] !== [] || ($range !== null && $current['range'] !== null)) {
                if ($current !== null) {
                    $entries[] = $current;
                }
                $current = $this->newEntry();
            }

            $header = $range === null ? $line : $this->removeFragment($line, $range->matchedText);

            if ($range !== null) {
                $current['range'] = $range;
            }
            if ($header !== '') {
                $current['header'][] = $header;
            }
            $current['source'][] = $line;
        }

        if ($current !== null) {
            $entries[] = $current;
        }

        return array_map(fn (array $entry) => $this->toSection($type, $entry), $entries);
    }

    /**
     * @return array{header: list<string>, bullets: array<int, string>, range: DateRange|null, source: list<string>}
     */
    private function newEntry(): array
    {
        return ['header' => [], 'bullets' => [], 'range' => null, 'source' => []];
    }

    /**
     * @param  array{header: list<string>, bullets: array<int, string>, range: DateRange|null, source: list<string>}  $entry
     */
    private function toSection(SectionType $type, array $entry): ParsedSection
    {
        $header = $this->joinWrappedLines($entry['header']);
        $parts = $header === [] ? [] : $this->splitHeaderLine($type, array_shift($header));

        $title = $parts[0] ?? null;

        if ($type === SectionType::Project) {
            // "Portfolio Website – Built with Next.js…": name, then what it is.
            $organization = null;
            $location = null;
            if (isset($parts[1])) {
                array_unshift($header, $parts[1]);
            }
        } else {
            $organization = $parts[1] ?? array_shift($header);
            $location = $parts[2] ?? null;
        }

        // Education often lists the school first; keep the degree as the title.
        if ($type === SectionType::Education && $title !== null && $this->looksLikeSchool($title) && ! $this->looksLikeSchool((string) $organization)) {
            [$title, $organization] = [$organization, $title];
        }

        $range = $entry['range'];

        return new ParsedSection(
            type: $type,
            title: $title,
            organization: $organization,
            location: $location,
            startDate: $range?->start,
            endDate: $range?->end,
            isCurrent: $range !== null && $range->isCurrent,
            description: $header === [] ? null : implode("\n", $header),
            bullets: array_values($entry['bullets']),
            sourceText: implode("\n", $entry['source']),
            confidence: match (true) {
                $title !== null && $range !== null => 0.85,
                $title !== null => 0.6,
                default => 0.4,
            },
        );
    }

    /**
     * Split a header into title / organization / location. Separators are
     * tried from most to least explicit, because dashes and commas also
     * appear inside titles ("Senior High School - TVL (IT - Web Dev)").
     *
     * @return list<string>
     */
    private function splitHeaderLine(SectionType $type, string $line): array
    {
        $parts = match (true) {
            str_contains($line, '|') => explode('|', $line),
            $type === SectionType::Project => preg_split('/\s+[—–-]\s+|:\s+/u', $line, 2),
            // ", Inc." and friends belong to the company name, not a new field.
            (bool) preg_match('/,\s/u', $line) => preg_split('/,(?!\s*(?:inc|llc|ltd|corp|co|gmbh|plc)\b)\s*/iu', $line, 3),
            default => preg_split('/\s+(?:—|–|-|@|at)\s+/u', $line),
        };

        return array_values(array_filter(array_map('trim', $parts ?: [$line]), fn (string $part) => $part !== ''));
    }

    /**
     * Rejoin header text that the PDF wrapped onto a new line: the previous
     * line ends mid-phrase ("…Institute of"), the next is a lowercase
     * continuation, or a single word finishes a comma list ("…, Iligan" + "City").
     *
     * @param  list<string>  $lines
     * @return list<string>
     */
    private function joinWrappedLines(array $lines): array
    {
        $joined = [];

        foreach ($lines as $line) {
            $previous = end($joined);
            $wrapped = $previous !== false && (
                preg_match('/(?:\b(?:of|and|the|for|in|at|with)|[,&–—-])$/iu', $previous)
                || preg_match('/^\p{Ll}/u', $line)
                || (! str_contains(trim($line), ' ') && str_contains($previous, ','))
            );

            if ($wrapped) {
                $joined[array_key_last($joined)] = $previous.' '.$line;
            } else {
                $joined[] = $line;
            }
        }

        return $joined;
    }

    /**
     * @param  list<string>  $lines
     */
    private function freeformSection(string $heading, array $lines): ParsedSection
    {
        return new ParsedSection(
            type: SectionType::Other,
            title: Str::title(mb_strtolower(trim($heading, " :\t"))),
            bullets: array_map(fn (string $line) => $this->stripBullet($line), $lines),
            sourceText: implode("\n", $lines),
            confidence: 0.7,
        );
    }

    /**
     * @param  list<string>  $lines
     * @return list<string>
     */
    private function skillTerms(array $lines): array
    {
        $terms = [];

        foreach ($lines as $line) {
            // Drop labels such as "Languages:" or "Frameworks -".
            $line = (string) preg_replace('/^[^:]{1,30}:\s*/u', '', $this->stripBullet($line));

            foreach (preg_split('/[,;|•·\/]|\s{2,}/u', $line) ?: [] as $term) {
                $term = trim($term, " \t.()");

                if ($term !== '' && mb_strlen($term) <= 40) {
                    $terms[] = $term;
                }
            }
        }

        return $terms;
    }

    /**
     * One "link" section per profile URL (LinkedIn, GitHub, portfolio, ...).
     *
     * @return list<ParsedSection>
     */
    private function linkSections(string $text): array
    {
        preg_match_all('/\b(?:https?:\/\/)?(?:www\.)?(?:linkedin\.com\/in|github\.com|gitlab\.com|behance\.net|dribbble\.com)\/[\w\-.\/]+|https?:\/\/[^\s,;)]+/iu', $text, $matches);

        $sections = [];

        foreach (array_unique($matches[0]) as $url) {
            $url = rtrim($url, '.');
            $host = (string) (parse_url(str_contains($url, '://') ? $url : "https://{$url}", PHP_URL_HOST) ?? $url);

            $sections[] = new ParsedSection(
                type: SectionType::Link,
                title: Str::of($host)->replaceStart('www.', '')->before('.')->title()->toString(),
                description: $url,
                confidence: 0.9,
            );
        }

        return $sections;
    }

    private function isBullet(string $line): bool
    {
        // A leading hyphen followed by a space is a bullet; "-2021" or a word is not.
        return (bool) preg_match('/^\s*(?:[•●▪◦‣∙·○■□➢►✓✔*]\s*|[\-–]\s+|\d{1,2}[.)]\s+)\S/u', $line);
    }

    private function stripBullet(string $line): string
    {
        return trim((string) preg_replace(self::BULLET, '', $line));
    }

    /**
     * Wrapped bullet lines start lowercase, or follow an unfinished long sentence.
     */
    private function continuesBullet(string $line, string $lastBullet): bool
    {
        if (preg_match('/^\p{Ll}/u', $line)) {
            return true;
        }

        $unfinished = ! preg_match('/[.!?;:]$/u', $lastBullet);

        return $unfinished && mb_strlen($line) > 60 && DateRange::find($line) === null;
    }

    private function removeFragment(string $line, string $fragment): string
    {
        $without = str_replace($fragment, '', $line);

        return trim((string) preg_replace('/\s*[|,–—\-]\s*$|^\s*[|,–—\-]\s*|\(\s*\)/u', '', $without));
    }

    private function looksLikeSchool(string $value): bool
    {
        return (bool) preg_match('/universit|college|school|institute|academy|polytechnic/i', $value);
    }
}
