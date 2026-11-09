<?php

namespace App\Http\Resources;

use App\Models\TailoredResume;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TailoredResume
 */
class TailoredResumeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'version' => $this->version,
            'status' => $this->status->value,
            'content' => $this->content,
            'flagged_claims' => $this->flagged_claims ?? [],
            'provider' => $this->provider,
            'error' => $this->error,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'posting' => $this->whenLoaded('posting', fn () => [
                'id' => $this->posting->id,
                'title' => $this->posting->title,
                'company' => $this->posting->company,
            ]),
        ];
    }
}
