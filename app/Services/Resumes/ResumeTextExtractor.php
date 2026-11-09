<?php

namespace App\Services\Resumes;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Smalot\PdfParser\Parser as PdfParser;
use Symfony\Component\Process\ExecutableFinder;
use Throwable;
use ZipArchive;

/**
 * Pulls plain text out of an uploaded PDF or DOCX.
 *
 * PDFs prefer the `pdftotext` binary (Poppler/Xpdf) when it is installed: it
 * keeps text that the pure-PHP parser drops with some fonts (common in LaTeX
 * résumés) and keeps lines in reading order. The PHP parser is the fallback.
 */
class ResumeTextExtractor
{
    public const PDF = 'application/pdf';

    public const DOCX = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

    /** Where pdftotext often lives when it isn't on PATH. */
    private const PDFTOTEXT_DIRS = [
        'C:\Program Files\Git\mingw64\bin',
        'C:\Program Files\poppler\Library\bin',
        '/usr/bin',
        '/usr/local/bin',
        '/opt/homebrew/bin',
    ];

    public function __construct(private readonly PdfParser $pdfParser) {}

    public function extract(string $absolutePath, string $mimeType): string
    {
        $text = match ($mimeType) {
            self::PDF => $this->fromPdf($absolutePath),
            self::DOCX => $this->fromDocx($absolutePath),
            default => throw new RuntimeException("Unsupported résumé type [{$mimeType}]."),
        };

        return self::clean($text);
    }

    /**
     * Make extracted text safe and tidy. PDFs often contain invalid UTF-8
     * (icon fonts, broken encodings), which would make every later regex
     * fail and PostgreSQL reject the text, so it is scrubbed first.
     */
    public static function clean(string $text): string
    {
        $text = mb_scrub($text, 'UTF-8');

        // Private-use glyphs are icon-font symbols (phone, email, GitHub logos), not words.
        $text = (string) preg_replace('/[\p{Co}\x{FFFD}]/u', '', $text);
        $text = (string) preg_replace('/[^\P{C}\n\t]/u', '', $text);

        $text = str_replace(["\r\n", "\r", "\u{00A0}", "\t"], ["\n", "\n", ' ', ' '], $text);
        $text = (string) preg_replace('/[ ]{2,}/u', ' ', $text);
        $text = (string) preg_replace("/\n{3,}/", "\n\n", $text);

        return trim($text);
    }

    private function fromPdf(string $path): string
    {
        $binary = $this->pdftotextBinary();

        if ($binary !== null) {
            try {
                $result = Process::timeout(30)->run([$binary, '-layout', '-enc', 'UTF-8', $path, '-']);

                if ($result->successful() && trim($result->output()) !== '') {
                    return $result->output();
                }
            } catch (Throwable $e) {
                Log::info('pdftotext failed, falling back to the PHP parser', ['error' => $e->getMessage()]);
            }
        }

        return $this->pdfParser->parseFile($path)->getText();
    }

    private function pdftotextBinary(): ?string
    {
        $configured = config('services.pdftotext.path');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return (new ExecutableFinder)->find('pdftotext', null, self::PDFTOTEXT_DIRS);
    }

    private function fromDocx(string $path): string
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new RuntimeException('The DOCX file could not be opened.');
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false) {
            throw new RuntimeException('The DOCX file has no document body.');
        }

        // Paragraphs and breaks become newlines; list items keep a bullet marker.
        $xml = str_replace(['<w:numPr>'], ['• '], $xml);
        $xml = (string) preg_replace(['/<\/w:p>/', '/<w:br[^>]*\/>/', '/<w:tab\/>/'], ["\n", "\n", "\t"], $xml);

        return html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
