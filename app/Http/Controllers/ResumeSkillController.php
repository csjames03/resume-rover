<?php

namespace App\Http\Controllers;

use App\Models\Skill;
use App\Services\Resumes\ResumeProfileEditor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ResumeSkillController extends Controller
{
    public function __construct(private readonly ResumeProfileEditor $editor) {}

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(['name' => ['required', 'string', 'max:60']]);
        $resume = $request->user()->activeResume;
        abort_if($resume === null, 404);

        $skill = $this->editor->addSkill($resume, $validated['name']);
        $this->toast("{$skill->name} added.");

        return back();
    }

    public function destroy(Request $request, Skill $skill): RedirectResponse
    {
        $resume = $request->user()->activeResume;
        abort_if($resume === null, 404);

        $this->editor->removeSkill($resume, $skill);
        $this->toast("{$skill->name} removed.");

        return back();
    }
}
