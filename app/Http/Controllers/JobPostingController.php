<?php

namespace App\Http\Controllers;

use App\Enums\AiTask;
use App\Enums\ApplicationStatus;
use App\Enums\MatchCategory;
use App\Enums\RemoteType;
use App\Http\Requests\JobPostingIndexRequest;
use App\Http\Resources\ApplicationEmailResource;
use App\Http\Resources\ApplicationResource;
use App\Http\Resources\JobPostingResource;
use App\Http\Resources\TailoredResumeResource;
use App\Models\JobPosting;
use App\Models\JobSource;
use App\Queries\JobPostingSearch;
use App\Services\Ai\AiAvailability;
use App\Services\Ai\AiGateway;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class JobPostingController extends Controller
{
    public function index(JobPostingIndexRequest $request, JobPostingSearch $search): Response
    {
        $user = $request->user();

        return Inertia::render('jobs/index', [
            'postings' => JobPostingResource::collection($search->paginate($user, $request->filters())),
            'filters' => $request->filters(),
            'options' => [
                'categories' => MatchCategory::options(),
                'remote' => RemoteType::options(),
                'statuses' => [...ApplicationStatus::options(), ['value' => 'untracked', 'label' => 'Not tracked']],
                'sources' => JobSource::query()->orderBy('name')->get(['id', 'name'])->map(fn (JobSource $s) => ['value' => (string) $s->id, 'label' => $s->name]),
            ],
            'hasResume' => $user->activeResume?->isParsed() ?? false,
        ]);
    }

    public function show(Request $request, JobPosting $posting, AiGateway $gateway, AiAvailability $availability): Response
    {
        $user = $request->user();
        $mine = fn ($query) => $query->where('user_id', $user->id);

        $posting->load(['source', 'matches' => fn ($q) => $mine($q)->with('resume'), 'applications' => $mine]);
        $application = $posting->applications->first()?->load('events');

        return Inertia::render('jobs/show', [
            'posting' => (new JobPostingResource($posting))->withDescription(),
            'application' => $application ? new ApplicationResource($application) : null,
            'tailoredResumes' => TailoredResumeResource::collection(
                $user->tailoredResumes()->where('job_posting_id', $posting->id)->latest('version')->get(),
            ),
            'applicationEmails' => ApplicationEmailResource::collection(
                $user->applicationEmails()->where('job_posting_id', $posting->id)->latest('id')->get(),
            ),
            'statuses' => ApplicationStatus::options(),
            'ai' => [
                'explain' => $gateway->isEnabled(AiTask::ExplainMatch),
                'tailor' => $gateway->isEnabled(AiTask::TailorResume),
                'usage' => $availability->status($user),
            ],
        ]);
    }
}
