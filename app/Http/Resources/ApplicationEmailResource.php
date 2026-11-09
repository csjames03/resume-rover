<?php

namespace App\Http\Resources;

use App\Models\ApplicationEmail;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ApplicationEmail
 */
class ApplicationEmailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'to' => $this->to,
            'subject' => $this->subject,
            'body' => $this->body,
            'attach_resume' => $this->attach_resume,
            'flags' => $this->flags ?? [],
            'status' => $this->status->value,
            'drafted_by' => $this->drafted_by,
            'error' => $this->error,
            'sent_at' => $this->sent_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'resume_filename' => $this->whenLoaded('resume', fn () => $this->resume?->original_filename),
            'posting' => $this->whenLoaded('posting', fn () => [
                'id' => $this->posting->id,
                'title' => $this->posting->title,
                'company' => $this->posting->company,
                'url' => $this->posting->url,
            ]),
        ];
    }
}
