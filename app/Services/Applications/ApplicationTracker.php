<?php

namespace App\Services\Applications;

use App\Enums\ApplicationEventType;
use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Owns every change to an application, so the history timeline can never
 * drift from the actual record.
 */
class ApplicationTracker
{
    public function track(User $user, JobPosting $posting, ApplicationStatus $status = ApplicationStatus::Saved): Application
    {
        return DB::transaction(function () use ($user, $posting, $status) {
            $application = $user->applications()->firstOrCreate(
                ['job_posting_id' => $posting->id],
                ['status' => $status, 'applied_at' => $status === ApplicationStatus::Applied ? now() : null],
            );

            if ($application->wasRecentlyCreated) {
                $this->log($application, ApplicationEventType::Created, to: $status);
            }

            return $application;
        });
    }

    /**
     * @param  array{status?: ApplicationStatus, notes?: string|null, next_action?: string|null, next_action_at?: string|null, tailored_resume_id?: int|null}  $changes
     */
    public function update(Application $application, array $changes): void
    {
        DB::transaction(function () use ($application, $changes) {
            $from = $application->status;
            $application->fill($changes);

            if ($application->isDirty('status') && $application->status === ApplicationStatus::Applied && $application->applied_at === null) {
                $application->applied_at = now();
            }

            $dirty = $application->getDirty();
            $application->save();

            if (array_key_exists('status', $dirty)) {
                $this->log($application, ApplicationEventType::StatusChanged, $from, $application->status);
            }

            if (array_key_exists('notes', $dirty)) {
                $this->log($application, ApplicationEventType::NoteUpdated);
            }

            if (array_key_exists('tailored_resume_id', $dirty) && $application->tailored_resume_id !== null) {
                $this->log($application, ApplicationEventType::TailoredResumeAttached, meta: ['tailored_resume_id' => $application->tailored_resume_id]);
            }
        });
    }

    public function archive(Application $application): void
    {
        $application->update(['archived_at' => now()]);
        $this->log($application, ApplicationEventType::Archived);
    }

    public function restore(Application $application): void
    {
        $application->update(['archived_at' => null]);
        $this->log($application, ApplicationEventType::Restored);
    }

    /**
     * @param  array<string, mixed>|null  $meta
     */
    private function log(
        Application $application,
        ApplicationEventType $type,
        ?ApplicationStatus $from = null,
        ?ApplicationStatus $to = null,
        ?array $meta = null,
    ): void {
        $application->events()->create([
            'type' => $type,
            'from_status' => $from,
            'to_status' => $to,
            'meta' => $meta,
        ]);
    }
}
