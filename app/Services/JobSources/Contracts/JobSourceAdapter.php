<?php

namespace App\Services\JobSources\Contracts;

use App\Models\JobSource;
use App\Services\JobSources\Data\JobPostingData;
use Illuminate\Http\Client\RequestException;

/**
 * One implementation per job board. Adapters only fetch and normalize;
 * storing, deduplicating and scoring happen elsewhere.
 */
interface JobSourceAdapter
{
    /**
     * @return iterable<JobPostingData>
     *
     * @throws RequestException when the source cannot be reached
     */
    public function fetch(JobSource $source): iterable;
}
