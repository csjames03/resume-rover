<?php

namespace App\Http\Resources;

use App\Models\JobSource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin JobSource
 */
class JobSourceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'driver' => $this->driver->value,
            'driver_label' => $this->driver->label(),
            'identifier' => $this->identifier,
            'title_filter' => $this->title_filter,
            'is_enabled' => $this->is_enabled,
            'fetch_interval_minutes' => $this->fetch_interval_minutes,
            'effective_interval_minutes' => $this->effectiveIntervalMinutes(),
            'last_fetched_at' => $this->last_fetched_at?->toIso8601String(),
            'last_status' => $this->last_status?->value,
            'last_error' => $this->last_error,
            'last_fetched_count' => $this->last_fetched_count,
            'open_postings_count' => $this->whenCounted('postings'),
            'compliance_notes' => $this->compliance_notes,
        ];
    }
}
