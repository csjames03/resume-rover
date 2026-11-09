<?php

namespace App\Services\Resumes\Parsing;

use Carbon\CarbonImmutable;

/**
 * A date range found in a line of résumé text, e.g. "Jan 2021 – Present".
 */
final readonly class DateRange
{
    private const MONTH = '(?:jan(?:uary)?|feb(?:ruary)?|mar(?:ch)?|apr(?:il)?|may|june?|july?|aug(?:ust)?|sep(?:t(?:ember)?)?|oct(?:ober)?|nov(?:ember)?|dec(?:ember)?)\.?';

    private const PRESENT = '(?:present|current|now|ongoing|today|date)';

    public function __construct(
        public ?CarbonImmutable $start,
        public ?CarbonImmutable $end,
        public bool $isCurrent,
        public string $matchedText,
    ) {}

    /**
     * Find the first date range (or single date) in a line.
     */
    public static function find(string $line): ?self
    {
        $date = '(?:'.self::MONTH.'\s*,?\s*\d{4}|\d{1,2}\/\d{4}|\d{4})';
        $separator = '\s*(?:-|–|—|to|until)\s*';
        $range = "/(?<start>{$date}){$separator}(?<end>{$date}|".self::PRESENT.')/iu';

        if (preg_match($range, $line, $m)) {
            $start = self::parseDate($m['start']);
            $isCurrent = (bool) preg_match('/^'.self::PRESENT.'$/i', trim($m['end']));

            // Implausible years (phone numbers, IDs) are not dates.
            if ($start !== null) {
                return new self($start, $isCurrent ? null : self::parseDate($m['end']), $isCurrent, $m[0]);
            }
        }

        // A line that starts with a year ("2025 Meta - Version Control") dates that entry.
        if (preg_match('/^\s*((?:19|20)\d{2})\s+(?=\p{L})/u', $line, $m)) {
            $date = self::parseDate($m[1]);

            return new self($date, $date, false, $m[1]);
        }

        // A lone "May 2022" usually means a graduation or certification date.
        if (preg_match('/'.self::MONTH.'\s*,?\s*\d{4}/iu', $line, $m)) {
            $date = self::parseDate($m[0]);

            return new self($date, $date, false, $m[0]);
        }

        return null;
    }

    /**
     * Two-column templates wrap the end of a range onto the next line:
     * "Feb 2024 – Jun Full Stack Developer" then "2025". Given that line and
     * the wrapped token ("2025", "Current"), return the line with the range
     * completed, or null when the line has no unfinished range.
     */
    public static function completeDangling(string $line, string $tail): ?string
    {
        if (! preg_match('/'.self::MONTH.'\s*,?\s*\d{4}\s*(?:-|–|—|to)\s*/iu', $line, $m, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $offset = $m[0][1] + strlen($m[0][0]);
        $rest = substr($line, $offset);

        // Already complete ("Feb 2024 – Jun 2025", "Feb 2024 – Present").
        if (preg_match('/^(?:'.self::MONTH.'\s*,?\s*\d{4}|\d{1,2}\/\d{4}|'.self::PRESENT.'\b)/iu', $rest)) {
            return null;
        }

        // Keep a dangling month ("– Jun") in front of the year that completes it.
        if (preg_match('/^'.self::MONTH.'(?=\s|$)/iu', $rest, $month)) {
            $offset += strlen($month[0]);
        }

        return trim(rtrim(substr($line, 0, $offset)).' '.$tail.' '.ltrim(substr($line, $offset)));
    }

    /**
     * Parse "Jan 2021", "01/2021", "2021" or "2021-01" into the first of that month.
     */
    public static function parseDate(?string $value): ?CarbonImmutable
    {
        $value = trim((string) $value, " \t.,");

        if ($value === '') {
            return null;
        }

        if (preg_match('/^(\d{4})(?:-(\d{1,2}))?/', $value, $m)) {
            return self::safeDate((int) $m[1], isset($m[2]) ? (int) $m[2] : 1);
        }

        if (preg_match('/^(\d{1,2})\/(\d{4})$/', $value, $m)) {
            return self::safeDate((int) $m[2], (int) $m[1]);
        }

        if (preg_match('/^([a-z]{3})[a-z]*\.?\s*,?\s*(\d{4})$/i', $value, $m)) {
            $month = (int) date('n', (int) strtotime("1 {$m[1]} 2000"));

            return self::safeDate((int) $m[2], $month);
        }

        return null;
    }

    private static function safeDate(int $year, int $month): ?CarbonImmutable
    {
        if ($year < 1950 || $year > 2100 || $month < 1 || $month > 12) {
            return null;
        }

        return CarbonImmutable::create($year, $month, 1);
    }
}
