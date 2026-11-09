<?php

namespace App\Http\Resources;

use App\Models\ResumeBullet;
use App\Models\ResumeSection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ResumeSection
 */
class ResumeSectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'title' => $this->title,
            'organization' => $this->organization,
            'location' => $this->location,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'is_current' => $this->is_current,
            'description' => $this->description,
            'confidence' => $this->confidence,
            'is_user_edited' => $this->is_user_edited,
            'bullets' => $this->bullets->map(fn (ResumeBullet $bullet) => ['id' => $bullet->id, 'text' => $bullet->text])->all(),
        ];
    }
}
