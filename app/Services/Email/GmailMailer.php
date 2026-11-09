<?php

namespace App\Services\Email;

use App\Models\ApplicationEmail;
use App\Models\EmailConnection;
use App\Services\Email\Exceptions\EmailNotSent;
use App\Services\Email\Exceptions\GmailNotConnected;
use App\Services\Resumes\ResumeUploader;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Sends one message through the Gmail API as the connected user.
 */
class GmailMailer
{
    private const SEND_URL = 'https://gmail.googleapis.com/gmail/v1/users/me/messages/send';

    public function __construct(private readonly GoogleOAuth $oauth) {}

    /**
     * @return string Gmail's message id
     *
     * @throws EmailNotSent when Gmail rejects the message or can't be reached
     * @throws GmailNotConnected when Google no longer accepts the connection
     */
    public function send(EmailConnection $connection, ApplicationEmail $email, string $senderName): string
    {
        $raw = $this->encode($this->buildMessage($connection, $email, $senderName));

        $response = $this->post($this->oauth->accessToken($connection), $raw);

        // An access token can be revoked before it expires; refresh once and retry.
        if ($response->status() === 401) {
            $response = $this->post($this->oauth->accessToken($connection, forceRefresh: true), $raw);
        }

        if ($response->failed()) {
            $reason = (string) $response->json('error.message', 'Gmail rejected the message.');

            throw new EmailNotSent('Gmail did not send the email: '.Str::limit($reason, 200));
        }

        return (string) $response->json('id');
    }

    private function buildMessage(EmailConnection $connection, ApplicationEmail $email, string $senderName): Email
    {
        $message = (new Email)
            ->from(new Address($connection->email, $senderName))
            ->to((string) $email->to)
            ->subject($email->subject)
            ->text($email->body);

        $resume = $email->resume;

        if ($email->attach_resume && $resume !== null && Storage::disk(ResumeUploader::DISK)->exists($resume->storage_path)) {
            $message->attachFromPath(
                Storage::disk(ResumeUploader::DISK)->path($resume->storage_path),
                $resume->original_filename,
                $resume->mime_type,
            );
        }

        return $message;
    }

    /**
     * Gmail wants the full RFC 822 message, base64url-encoded.
     */
    private function encode(Email $message): string
    {
        return rtrim(strtr(base64_encode($message->toString()), '+/', '-_'), '=');
    }

    private function post(string $accessToken, string $raw): Response
    {
        try {
            return Http::withToken($accessToken)->timeout(30)->post(self::SEND_URL, ['raw' => $raw]);
        } catch (ConnectionException) {
            throw new EmailNotSent('Could not reach Gmail. Check your connection and try again.');
        }
    }
}
