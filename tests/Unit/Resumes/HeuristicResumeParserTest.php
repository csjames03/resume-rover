<?php

namespace Tests\Unit\Resumes;

use App\Enums\SectionType;
use App\Services\Resumes\Parsing\HeuristicResumeParser;
use App\Services\Resumes\Parsing\ParsedSection;
use PHPUnit\Framework\TestCase;

class HeuristicResumeParserTest extends TestCase
{
    private const RESUME = <<<'TEXT'
        Juan Dela Cruz
        juan@example.com · github.com/juandc · linkedin.com/in/juandc

        SUMMARY
        Backend developer focused on Laravel and PostgreSQL.

        WORK EXPERIENCE
        Software Engineer | Acme Corp | Manila
        Jan 2021 – Present
        • Built REST APIs in Laravel serving 2M requests a day
        • Cut report generation time by 40% by moving work to queues and
        caching expensive queries
        Junior Developer at Beta Labs
        Jun 2019 - Dec 2020
        - Maintained Vue.js dashboards

        EDUCATION
        University of the Philippines
        BS Computer Science, 2015 - 2019

        Technical Skills
        Languages: PHP, JavaScript, SQL
        Tools: Docker, Git
        TEXT;

    public function test_it_splits_sections_and_entries(): void
    {
        $parsed = (new HeuristicResumeParser)->parse(self::RESUME);
        $experience = $this->ofType($parsed->sections, SectionType::Experience);

        $this->assertCount(2, $experience);

        [$current, $previous] = $experience;
        $this->assertSame('Software Engineer', $current->title);
        $this->assertSame('Acme Corp', $current->organization);
        $this->assertSame('Manila', $current->location);
        $this->assertTrue($current->isCurrent);
        $this->assertSame('2021-01', $current->startDate?->format('Y-m'));
        $this->assertCount(2, $current->bullets);

        $this->assertSame('Junior Developer', $previous->title);
        $this->assertSame('Beta Labs', $previous->organization);
        $this->assertSame(['Maintained Vue.js dashboards'], $previous->bullets);
    }

    public function test_wrapped_bullet_lines_are_joined(): void
    {
        $parsed = (new HeuristicResumeParser)->parse(self::RESUME);
        $current = $this->ofType($parsed->sections, SectionType::Experience)[0];

        $this->assertSame(
            'Cut report generation time by 40% by moving work to queues and caching expensive queries',
            $current->bullets[1],
        );
    }

    public function test_it_keeps_the_degree_as_education_title(): void
    {
        $parsed = (new HeuristicResumeParser)->parse(self::RESUME);
        $education = $this->ofType($parsed->sections, SectionType::Education)[0];

        $this->assertSame('BS Computer Science', $education->title);
        $this->assertSame('University of the Philippines', $education->organization);
    }

    public function test_it_collects_skills_summary_and_links(): void
    {
        $parsed = (new HeuristicResumeParser)->parse(self::RESUME);

        $this->assertSame(['PHP', 'JavaScript', 'SQL', 'Docker', 'Git'], $parsed->skillTerms);
        $this->assertStringContainsString('Laravel', (string) $this->ofType($parsed->sections, SectionType::Summary)[0]->description);
        $this->assertCount(2, $this->ofType($parsed->sections, SectionType::Link));
    }

    /**
     * Two-column templates (e.g. LaTeX moderncv) put dates in a left column,
     * so the end of a range wraps onto the next line of extracted text.
     */
    public function test_two_column_layouts_with_wrapped_dates(): void
    {
        $text = <<<'TEXT'
            Experience
            Feb 2024 – Jun Full Stack Web Developer, 8Drive Cars, Remote
            2025
            ○ Developed ERP system using Next.js and Prisma.
            Jul 2025 – Full Stack Engineer, AVI, Remote
            Current ○ Helped plan the features and workflow
            ○ Developed secure API endpoints
            1/2
            Feb 2026 – Front End Engineer (Part-Time), Continental Cargo Association, Inc. (CCA), Remote
            Current ○ Built responsive interfaces
            Education
            2021 – 2025 Bachelor of Science in Computer Science, Mindanao State University - Iligan Institute of
            Technology (MSU-IIT), Iligan City
            Certifications
            2025 Meta - Programming with JavaScript
            2025 Meta - Version Control
            TEXT;

        $parsed = (new HeuristicResumeParser)->parse($text);
        $experience = $this->ofType($parsed->sections, SectionType::Experience);

        $this->assertCount(3, $experience);
        $this->assertSame(['Full Stack Web Developer', '8Drive Cars', 'Remote'], [$experience[0]->title, $experience[0]->organization, $experience[0]->location]);
        $this->assertSame('2025-06', $experience[0]->endDate?->format('Y-m'));
        $this->assertTrue($experience[1]->isCurrent);
        $this->assertCount(2, $experience[1]->bullets, 'the bullet after "Current" is kept and the page counter is dropped');
        $this->assertSame('Continental Cargo Association, Inc. (CCA)', $experience[2]->organization);

        $education = $this->ofType($parsed->sections, SectionType::Education)[0];
        $this->assertSame('Mindanao State University - Iligan Institute of Technology (MSU-IIT)', $education->organization);
        $this->assertSame('Iligan City', $education->location);

        $this->assertCount(2, $this->ofType($parsed->sections, SectionType::Certification));
    }

    /**
     * @param  list<ParsedSection>  $sections
     * @return list<ParsedSection>
     */
    private function ofType(array $sections, SectionType $type): array
    {
        return array_values(array_filter($sections, fn (ParsedSection $s) => $s->type === $type));
    }
}
