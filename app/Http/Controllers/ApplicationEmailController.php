<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateApplicationEmailRequest;
use App\Http\Resources\ApplicationEmailResource;
use App\Models\ApplicationEmail;
use App\Models\JobPosting;
use App\Services\Email\ApplicationEmailDrafter;
use App\Services\Email\ApplicationEmailSender;
use App\Services\Email\Exceptions\EmailNotSent;
use App\Services\Email\Exceptions\GmailNotConnected;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ApplicationEmailController extends Controller
{
    /**
     * "Apply by email": write a draft and open it for review.
     */
    public function store(Request $request, JobPosting $posting, ApplicationEmailDrafter $drafter): RedirectResponse
    {
        try {
            $email = $drafter->draft($request->user(), $posting);
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'error');

            return back();
        }

        return to_route('application-emails.show', $email);
    }

    public function show(Request $request, ApplicationEmail $applicationEmail): Response
    {
        Gate::authorize('view', $applicationEmail);

        $applicationEmail->load(['posting', 'resume']);
        $connection = $request->user()->emailConnection;

        return Inertia::render('application-emails/show', [
            'email' => new ApplicationEmailResource($applicationEmail),
            'gmail' => $connection ? [
                'email' => $connection->email,
                'needs_reconnect' => ! $connection->isUsable(),
            ] : null,
        ]);
    }

    public function update(UpdateApplicationEmailRequest $request, ApplicationEmail $applicationEmail, ApplicationEmailDrafter $drafter): RedirectResponse
    {
        Gate::authorize('update', $applicationEmail);
        abort_unless($applicationEmail->isEditable(), 409, 'This email was already sent.');

        $applicationEmail->fill($request->validated());

        // Re-check claims whenever the wording changes.
        if ($applicationEmail->isDirty('body') && $applicationEmail->resume !== null) {
            $applicationEmail->flags = $drafter->flagsFor($applicationEmail->body, $applicationEmail->resume);
        }

        $applicationEmail->save();
        $this->toast('Draft saved.');

        return back();
    }

    /**
     * The explicit approval step: nothing is sent until the user clicks this.
     */
    public function send(ApplicationEmail $applicationEmail, ApplicationEmailSender $sender): RedirectResponse
    {
        Gate::authorize('update', $applicationEmail);

        try {
            $sender->send($applicationEmail);
        } catch (GmailNotConnected|EmailNotSent $e) {
            $this->toast($e->getMessage(), 'error');

            return back();
        }

        $this->toast("Sent to {$applicationEmail->to}. The job is now marked Applied.");

        return back();
    }

    public function destroy(ApplicationEmail $applicationEmail): RedirectResponse
    {
        Gate::authorize('delete', $applicationEmail);
        abort_unless($applicationEmail->isEditable(), 409, 'Sent emails are kept as a record.');

        $postingId = $applicationEmail->job_posting_id;
        $applicationEmail->delete();
        $this->toast('Draft discarded.');

        return to_route('jobs.show', $postingId);
    }
}
