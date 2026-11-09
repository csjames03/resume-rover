<?php

namespace App\Http\Resources;

use App\Models\Resume;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Resume
 */
class ResumeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'original_filename' => $this->original_filename,
            'file_size' => $this->file_size,
            'parse_status' => $this->parse_status->value,
            'parse_error' => $this->parse_error,
            'parser' => $this->parser,
            'parsed_at' => $this->parsed_at?->toIso8601String(),
            'profile_version' => $this->profile_version,
            'uploaded_at' => $this->created_at?->toIso8601String(),
            'sections' => ResumeSectionResource::collection($this->whenLoaded('sections')),
            'skills' => SkillResource::collection($this->whenLoaded('skills')),
        ];
    }
}
