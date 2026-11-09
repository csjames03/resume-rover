<?php

namespace App\Http\Controllers;

use App\Enums\GenerationStatus;
use App\Jobs\ExplainMatch;
use App\Models\JobPosting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MatchExplanationController extends Controller
{
    /**
     * Queue an AI explanation of the user's score for this posting.
     */
    public function store(Request $request, JobPosting $posting): RedirectResponse
    {
        $match = $request->user()->jobMatches()->where('job_posting_id', $posting->id)->firstOrFail();

        if ($match->explanation_status !== GenerationStatus::Pending) {
            $match->update(['explanation_status' => GenerationStatus::Pending]);
            ExplainMatch::dispatch($match->id);
        }

        $this->toast('Writing an explanation…', 'info');

        return back();
    }
}
