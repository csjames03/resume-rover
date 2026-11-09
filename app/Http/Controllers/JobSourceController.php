<?php

namespace App\Http\Controllers;

use App\Enums\JobSourceDriver;
use App\Http\Requests\JobSourceRequest;
use App\Http\Resources\JobSourceResource;
use App\Jobs\IngestJobSource;
use App\Models\JobSource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Job sources are shared configuration (postings are the same for every user):
 * anyone can see them, only admins can change them.
 */
class JobSourceController extends Controller
{
    public function index(Request $request): Response
    {
        $sources = JobSource::query()
            ->withCount(['postings' => fn (Builder $q) => $q->whereNull('closed_at')])
            ->orderBy('name')
            ->get();

        return Inertia::render('sources/index', [
            'sources' => JobSourceResource::collection($sources),
            'canManage' => $request->user()->can('manage-job-sources'),
            'drivers' => collect(JobSourceDriver::cases())->map(fn (JobSourceDriver $driver) => [
                'value' => $driver->value,
                'label' => $driver->label().($driver->requiresIdentifier() ? ' · company board' : ' · job board'),
                'hint' => $driver->identifierHint(),
                'requires_identifier' => $driver->requiresIdentifier(),
                'minimum_interval' => $driver->minimumIntervalMinutes(),
            ]),
        ]);
    }

    public function store(JobSourceRequest $request): RedirectResponse
    {
        Gate::authorize('manage-job-sources');

        $source = JobSource::query()->create($request->validated());
        IngestJobSource::dispatch($source->id);

        $this->toast("{$source->name} added. Fetching its jobs now…");

        return back();
    }

    public function update(JobSourceRequest $request, JobSource $source): RedirectResponse
    {
        Gate::authorize('manage-job-sources');

        $source->update($request->validated());
        $this->toast("{$source->name} saved.");

        return back();
    }

    public function destroy(JobSource $source): RedirectResponse
    {
        Gate::authorize('manage-job-sources');

        // Postings (and the user's history on them) are kept: the source is just switched off.
        if ($source->postings()->exists()) {
            $source->update(['is_enabled' => false]);
            $this->toast("{$source->name} has postings, so it was disabled instead of deleted.", 'info');

            return back();
        }

        $source->delete();
        $this->toast("{$source->name} removed.");

        return back();
    }

    public function fetch(JobSource $source): RedirectResponse
    {
        Gate::authorize('manage-job-sources');

        if (! $source->canFetchNow()) {
            $this->toast("{$source->name} was fetched recently. Its terms allow a refresh every {$source->driver->minimumIntervalMinutes()} minutes.", 'warning');

            return back();
        }

        IngestJobSource::dispatch($source->id);
        $this->toast("Fetching {$source->name}…", 'info');

        return back();
    }
}
