<?php

namespace App\Http\Controllers;

use App\Enums\AiTask;
use App\Enums\GenerationStatus;
use App\Jobs\ExplainMatch;
use App\Models\JobPosting;
use App\Services\Ai\AiAvailability;
use App\Services\Ai\Exceptions\AiLimitReached;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MatchExplanationController extends Controller
{
    /**
     * Queue an AI explanation of the user's score for this posting.
     */
    public function store(Request $request, JobPosting $posting, AiAvailability $ai): RedirectResponse
    {
        $match = $request->user()->jobMatches()->where('job_posting_id', $posting->id)->firstOrFail();

        if ($match->explanation_status !== GenerationStatus::Pending) {
            try {
                $ai->claim($request->user(), AiTask::ExplainMatch);
            } catch (AiLimitReached $e) {
                $this->toast($e->getMessage(), 'warning');

                return back();
            }

            $match->update(['explanation_status' => GenerationStatus::Pending]);
            ExplainMatch::dispatch($match->id);
        }

        $this->toast('Writing an explanation…', 'info');

        return back();
    }
}
