<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateTailoredResumeRequest;
use App\Http\Resources\ResumeResource;
use App\Http\Resources\TailoredResumeResource;
use App\Models\JobPosting;
use App\Models\TailoredResume;
use App\Services\Tailoring\ResumeTailor;
use App\Services\Tailoring\TailoredContent;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TailoredResumeController extends Controller
{
    public function __construct(private readonly ResumeTailor $tailor) {}

    public function store(Request $request, JobPosting $posting): RedirectResponse
    {
        try {
            $tailored = $this->tailor->request($request->user(), $posting);
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'error');

            return back();
        }

        return to_route('tailored-resumes.show', $tailored);
    }

    public function show(TailoredResume $tailoredResume): Response
    {
        Gate::authorize('view', $tailoredResume);

        $tailoredResume->load(['posting', 'resume.sections.bullets', 'resume.skills']);

        return Inertia::render('tailored-resumes/show', [
            'tailored' => new TailoredResumeResource($tailoredResume),
            'original' => new ResumeResource($tailoredResume->resume),
        ]);
    }

    public function update(UpdateTailoredResumeRequest $request, TailoredResume $tailoredResume): RedirectResponse
    {
        Gate::authorize('update', $tailoredResume);

        $this->tailor->applyEdits($tailoredResume, $request->validated('summary'), (array) $request->validated('sections', []));
        $this->toast('Draft saved.');

        return back();
    }

    public function approve(TailoredResume $tailoredResume): RedirectResponse
    {
        Gate::authorize('update', $tailoredResume);

        $this->tailor->approve($tailoredResume);
        $this->toast('Draft approved. You can download it now.');

        return back();
    }

    public function download(Request $request, TailoredResume $tailoredResume): StreamedResponse
    {
        Gate::authorize('view', $tailoredResume);

        $tailoredResume->load('posting');
        $filename = Str::slug("resume-{$tailoredResume->posting->company}-{$tailoredResume->posting->title}-v{$tailoredResume->version}").'.md';
        $markdown = TailoredContent::toMarkdown($tailoredResume, $request->user()->name);

        return response()->streamDownload(fn () => print ($markdown), $filename, ['Content-Type' => 'text/markdown']);
    }
}
