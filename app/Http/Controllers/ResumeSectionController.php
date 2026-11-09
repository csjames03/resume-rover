<?php

namespace App\Http\Controllers;

use App\Http\Requests\ResumeSectionRequest;
use App\Models\ResumeSection;
use App\Services\Resumes\ResumeProfileEditor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class ResumeSectionController extends Controller
{
    public function __construct(private readonly ResumeProfileEditor $editor) {}

    public function store(ResumeSectionRequest $request): RedirectResponse
    {
        $resume = $request->user()->activeResume;
        abort_if($resume === null, 404);

        $this->editor->createSection($resume, $request->sectionAttributes(), $request->bullets());
        $this->toast('Section added.');

        return back();
    }

    public function update(ResumeSectionRequest $request, ResumeSection $section): RedirectResponse
    {
        Gate::authorize('update', $section->resume);

        $this->editor->updateSection($section, $request->sectionAttributes(), $request->bullets());
        $this->toast('Section saved. Matches will refresh shortly.');

        return back();
    }

    public function destroy(ResumeSection $section): RedirectResponse
    {
        Gate::authorize('update', $section->resume);

        $this->editor->deleteSection($section);
        $this->toast('Section removed.');

        return back();
    }
}
