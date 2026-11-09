<?php

namespace App\Http\Controllers;

use App\Enums\ApplicationStatus;
use App\Http\Requests\UpdateApplicationRequest;
use App\Http\Resources\ApplicationResource;
use App\Models\Application;
use App\Models\JobPosting;
use App\Services\Applications\ApplicationTracker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ApplicationController extends Controller
{
    public function __construct(private readonly ApplicationTracker $tracker) {}

    public function index(Request $request): Response
    {
        $archived = $request->boolean('archived');

        $applications = $request->user()->applications()
            ->with('posting')
            ->when($archived, fn ($q) => $q->whereNotNull('archived_at'), fn ($q) => $q->active())
            ->latest('updated_at')
            ->get();

        return Inertia::render('applications/index', [
            'applications' => ApplicationResource::collection($applications),
            'statuses' => ApplicationStatus::options(),
            'showingArchived' => $archived,
        ]);
    }

    /**
     * Start tracking a posting (the "Save" button).
     */
    public function store(Request $request, JobPosting $posting): RedirectResponse
    {
        $validated = $request->validate(['status' => ['nullable', Rule::enum(ApplicationStatus::class)]]);

        $this->tracker->track(
            $request->user(),
            $posting,
            ApplicationStatus::tryFrom($validated['status'] ?? '') ?? ApplicationStatus::Saved,
        );

        $this->toast('Added to your applications.');

        return back();
    }

    public function update(UpdateApplicationRequest $request, Application $application): RedirectResponse
    {
        Gate::authorize('update', $application);

        $this->tracker->update($application, $request->changes());
        $this->toast('Application updated.');

        return back();
    }

    public function archive(Application $application): RedirectResponse
    {
        Gate::authorize('update', $application);

        $this->tracker->archive($application);
        $this->toast('Application archived.');

        return back();
    }

    public function restore(Application $application): RedirectResponse
    {
        Gate::authorize('update', $application);

        $this->tracker->restore($application);
        $this->toast('Application restored.');

        return back();
    }
}
