<?php

namespace App\Http\Resources;

use App\Models\JobPosting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Expects `matches` and `applications` to be loaded already filtered to the
 * current user, so `first()` is that user's record.
 *
 * @mixin JobPosting
 */
class JobPostingResource extends JsonResource
{
    /** Include the full description (detail page only). */
    private bool $withDescription = false;

    public function withDescription(): static
    {
        $this->withDescription = true;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $match = $this->relationLoaded('matches') ? $this->matches->first() : null;
        $application = $this->relationLoaded('applications') ? $this->applications->first() : null;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'company' => $this->company,
            'location' => $this->location,
            'remote_type' => $this->remote_type->value,
            'remote_label' => $this->remote_type->label(),
            'employment_type' => $this->employment_type?->label(),
            'salary_text' => $this->salary_text,
            'url' => $this->url,
            'posted_at' => $this->posted_at?->toIso8601String(),
            'first_seen_at' => $this->first_seen_at->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'source' => $this->whenLoaded('source', fn () => [
                'name' => $this->source->name,
                'driver' => $this->source->driver->value,
                // Several boards' API terms require crediting them as the source.
                'attribution' => $this->source->driver->attribution(),
            ]),
            'match' => $match ? new JobMatchResource($match) : null,
            'application' => $application ? new ApplicationResource($application) : null,
            'description' => $this->when($this->withDescription, $this->description),
        ];
    }
}
