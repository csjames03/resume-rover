<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $resume_section_id
 * @property int $position
 * @property string $text
 */
#[Fillable(['resume_section_id', 'position', 'text'])]
class ResumeBullet extends Model
{
    /**
     * @return BelongsTo<ResumeSection, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(ResumeSection::class, 'resume_section_id');
    }
}
