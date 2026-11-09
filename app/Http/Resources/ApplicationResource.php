<?php

namespace App\Http\Resources;

use App\Models\Application;
use App\Models\ApplicationEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Application
 */
class ApplicationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'applied_at' => $this->applied_at?->toIso8601String(),
            'next_action' => $this->next_action,
            'next_action_at' => $this->next_action_at?->toDateString(),
            'notes' => $this->notes,
            'tailored_resume_id' => $this->tailored_resume_id,
            'archived_at' => $this->archived_at?->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'posting' => $this->whenLoaded('posting', fn () => [
                'id' => $this->posting->id,
                'title' => $this->posting->title,
                'company' => $this->posting->company,
                'closed_at' => $this->posting->closed_at?->toIso8601String(),
            ]),
            'events' => $this->whenLoaded('events', fn () => $this->events->map(fn (ApplicationEvent $event) => [
                'id' => $event->id,
                'type' => $event->type->value,
                'label' => $event->type->label(),
                'from_status' => $event->from_status?->label(),
                'to_status' => $event->to_status?->label(),
                'created_at' => $event->created_at->toIso8601String(),
            ])),
        ];
    }
}
