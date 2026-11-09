<?php

namespace App\Services\JobSources\Support;

/**
 * Converts job-board HTML into plain text with line breaks. Postings are
 * stored and shown as text, so no third-party HTML ever reaches the browser.
 */
final class HtmlToText
{
    public static function convert(?string $html): string
    {
        $html = (string) $html;

        // Greenhouse double-encodes its HTML (&lt;p&gt;...).
        if (str_contains($html, '&lt;')) {
            $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        $html = (string) preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', '', $html);
        $html = (string) preg_replace('/<li\b[^>]*>/i', "\n• ", $html);
        $html = (string) preg_replace('/<br\s*\/?>|<\/(p|div|li|ul|ol|h[1-6]|tr|section)>/i', "\n", $html);

        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\u{00A0}", ' ', $text);
        $text = (string) preg_replace('/[ \t]+/u', ' ', $text);
        $text = (string) preg_replace('/ *\n */', "\n", $text);
        $text = (string) preg_replace("/\n{3,}/", "\n\n", $text);

        return trim($text);
    }
}
