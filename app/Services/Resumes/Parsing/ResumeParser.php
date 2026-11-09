<?php

namespace App\Services\Resumes\Parsing;

interface ResumeParser
{
    /**
     * Turn extracted résumé text into structured sections.
     */
    public function parse(string $text): ParsedResume;
}
