<?php

namespace App\Http\Controllers;

use App\Enums\ResumeParseStatus;
use App\Enums\SectionType;
use App\Http\Requests\StoreResumeRequest;
use App\Http\Resources\ResumeResource;
use App\Jobs\ParseResume;
use App\Models\Resume;
use App\Models\Skill;
use App\Services\Resumes\ResumeUploader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ResumeController extends Controller
{
    public function show(Request $request): Response
    {
        $resume = $request->user()->activeResume?->load(['sections.bullets', 'skills']);

        return Inertia::render('resume/show', [
            'resume' => $resume ? new ResumeResource($resume) : null,
            'sectionTypes' => SectionType::options(),
            'skillSuggestions' => Inertia::defer(fn () => Skill::query()->orderBy('name')->pluck('name')),
        ]);
    }

    public function store(StoreResumeRequest $request, ResumeUploader $uploader): RedirectResponse
    {
        $uploader->upload($request->user(), $request->file('file'));

        $this->toast('Résumé uploaded. Parsing it now…');

        return to_route('resume.show');
    }

    /**
     * Parse the stored file again, replacing the current sections.
     */
    public function reparse(Resume $resume): RedirectResponse
    {
        Gate::authorize('update', $resume);

        $resume->update(['parse_status' => ResumeParseStatus::Pending]);
        ParseResume::dispatch($resume->id);

        $this->toast('Re-parsing your résumé…', 'info');

        return to_route('resume.show');
    }

    /**
     * Delete the file and everything parsed from it.
     */
    public function destroy(Resume $resume): RedirectResponse
    {
        Gate::authorize('delete', $resume);

        Storage::disk(ResumeUploader::DISK)->delete($resume->storage_path);
        $resume->delete();

        $this->toast('Résumé and its parsed data were deleted.');

        return to_route('resume.show');
    }
}
