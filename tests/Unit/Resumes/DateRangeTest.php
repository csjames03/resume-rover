<?php

namespace Tests\Unit\Resumes;

use App\Services\Resumes\Parsing\DateRange;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DateRangeTest extends TestCase
{
    /**
     * @return array<string, array{string, string, string|null, bool}>
     */
    public static function ranges(): array
    {
        return [
            'month names' => ['Software Engineer | Jan 2020 – Mar 2022', '2020-01', '2022-03', false],
            'present' => ['Acme Corp, June 2021 - Present', '2021-06', null, true],
            'numeric months' => ['06/2019 to 08/2020', '2019-06', '2020-08', false],
            'years only' => ['BS Computer Science 2016 - 2020', '2016-01', '2020-01', false],
        ];
    }

    #[DataProvider('ranges')]
    public function test_it_finds_date_ranges(string $line, string $start, ?string $end, bool $current): void
    {
        $range = DateRange::find($line);

        $this->assertNotNull($range);
        $this->assertSame($start, $range->start?->format('Y-m'));
        $this->assertSame($end, $range->end?->format('Y-m'));
        $this->assertSame($current, $range->isCurrent);
    }

    public function test_phone_numbers_are_not_dates(): void
    {
        $this->assertNull(DateRange::find('Call me: 0917 - 1234'));
    }

    public function test_it_completes_ranges_wrapped_onto_the_next_line(): void
    {
        $this->assertSame('Feb 2024 – Jun 2025 Developer, Acme', DateRange::completeDangling('Feb 2024 – Jun Developer, Acme', '2025'));
        $this->assertSame('Jul 2025 – Current Engineer', DateRange::completeDangling('Jul 2025 – Engineer', 'Current'));
        $this->assertNull(DateRange::completeDangling('Feb 2024 – Jun 2025 Developer', '2026'), 'already complete');
        $this->assertNull(DateRange::completeDangling('Certifications', '2025'), 'no range at all');
    }

    public function test_a_leading_year_dates_the_line(): void
    {
        $this->assertSame('2025', DateRange::find('2025 Meta - Version Control')?->start?->format('Y'));
    }

    public function test_it_parses_single_dates(): void
    {
        $this->assertSame('2023-05', DateRange::parseDate('May 2023')?->format('Y-m'));
        $this->assertSame('2023-05', DateRange::parseDate('2023-05')?->format('Y-m'));
        $this->assertNull(DateRange::parseDate('soon'));
    }
}
