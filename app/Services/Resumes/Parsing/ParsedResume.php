<?php

namespace App\Services\Resumes\Parsing;

final readonly class ParsedResume
{
    /**
     * @param  list<ParsedSection>  $sections
     * @param  list<string>  $skillTerms  skills listed explicitly (e.g. a "Skills" section)
     * @param  string  $parser  which parser produced this, e.g. "heuristic" or "ai:gemini"
     */
    public function __construct(
        public array $sections,
        public array $skillTerms,
        public string $parser,
    ) {}
}
