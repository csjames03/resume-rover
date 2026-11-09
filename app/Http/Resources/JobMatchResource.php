<?php

namespace App\Http\Resources;

use App\Models\JobMatch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin JobMatch
 */
class JobMatchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'score' => $this->score,
            'category' => $this->category->value,
            'category_label' => $this->category->label(),
            'strengths' => $this->strengths,
            'gaps' => $this->gaps,
            'scored_at' => $this->scored_at->toIso8601String(),
            'is_stale' => $this->relationLoaded('resume') && $this->isStale(),
            'explanation' => $this->explanation,
            'explanation_status' => $this->explanation_status?->value,
        ];
    }
}
