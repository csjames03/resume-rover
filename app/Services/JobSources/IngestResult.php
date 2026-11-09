<?php

namespace App\Services\JobSources;

final class IngestResult
{
    public int $fetched = 0;

    public int $created = 0;

    public int $updated = 0;

    public int $closed = 0;

    public int $duplicates = 0;

    /** Skipped by the source's title filter. */
    public int $filtered = 0;

    /** @var list<int> new postings or postings whose description changed */
    public array $changedPostingIds = [];

    public function summary(): string
    {
        return "{$this->fetched} fetched, {$this->created} new, {$this->updated} updated, {$this->closed} closed, "
            ."{$this->duplicates} duplicates skipped, {$this->filtered} filtered out";
    }
}
