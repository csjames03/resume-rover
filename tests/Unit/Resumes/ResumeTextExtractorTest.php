<?php

namespace Tests\Unit\Resumes;

use App\Services\Resumes\ResumeTextExtractor;
use PHPUnit\Framework\TestCase;

class ResumeTextExtractorTest extends TestCase
{
    public function test_invalid_utf8_from_icon_fonts_does_not_wipe_the_text(): void
    {
        // LaTeX moderncv icons come out as invalid bytes and private-use glyphs.
        $raw = "James C. Ocao\n\xC3\x28+63 966 382 0717\n\u{F0E0}csjames@example.com\nProfessional Summary";

        $clean = ResumeTextExtractor::clean($raw);

        $this->assertStringContainsString('James C. Ocao', $clean);
        $this->assertStringContainsString('csjames@example.com', $clean);
        $this->assertStringContainsString('Professional Summary', $clean);
        $this->assertTrue(mb_check_encoding($clean, 'UTF-8'));
        $this->assertStringNotContainsString("\u{F0E0}", $clean);
    }

    public function test_it_collapses_whitespace(): void
    {
        $this->assertSame("a b\n\nc", ResumeTextExtractor::clean("a   b\r\n\r\n\r\n\r\nc  "));
    }
}
