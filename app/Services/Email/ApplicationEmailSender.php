<?php

namespace App\Services\Email;

use App\Enums\ApplicationEventType;
use App\Enums\ApplicationStatus;
use App\Enums\EmailStatus;
use App\Models\ApplicationEmail;
use App\Services\Applications\ApplicationTracker;
use App\Services\Email\Exceptions\EmailNotSent;
use App\Services\Email\Exceptions\GmailNotConnected;
use Illuminate\Support\Str;

/**
 * The only way an email leaves the app: the user clicked Send on a draft
 * they reviewed. On success the job is marked Applied in the tracker.
 */
class ApplicationEmailSender
{
    public function __construct(
        private readonly GmailMailer $mailer,
        private readonly ApplicationTracker $tracker,
    ) {}

    /**
     * @throws GmailNotConnected|EmailNotSent with a message safe to show the user
     */
    public function send(ApplicationEmail $email): void
    {
        $email->loadMissing('user.emailConnection', 'posting', 'resume');
        $user = $email->user;
        $connection = $user->emailConnection;

        if ($connection === null) {
            throw new GmailNotConnected('Connect Gmail in Settings → Email before sending.');
        }

        if (! $email->isEditable()) {
            throw new EmailNotSent('This email was already sent.');
        }

        if (blank($email->to) || ! filter_var($email->to, FILTER_VALIDATE_EMAIL)) {
            throw new EmailNotSent('Add a valid recipient address first.');
        }

        try {
            $messageId = $this->mailer->send($connection, $email, $user->name);
        } catch (GmailNotConnected|EmailNotSent $e) {
            $email->update(['status' => EmailStatus::Failed, 'error' => Str::limit($e->getMessage(), 500)]);

            throw $e;
        }

        $email->update([
            'status' => EmailStatus::Sent,
            'gmail_message_id' => $messageId,
            'sent_at' => now(),
            'error' => null,
        ]);

        $this->recordInTracker($email);
    }

    private function recordInTracker(ApplicationEmail $email): void
    {
        $application = $this->tracker->track($email->user, $email->posting, ApplicationStatus::Applied);

        if ($application->status !== ApplicationStatus::Applied) {
            $this->tracker->update($application, ['status' => ApplicationStatus::Applied]);
        }

        $application->events()->create([
            'type' => ApplicationEventType::EmailSent,
            'meta' => ['application_email_id' => $email->id, 'to' => $email->to],
        ]);
    }
}
