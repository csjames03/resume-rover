<?php

namespace App\Models;

use App\Enums\RequirementKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One thing a posting asks for: either a skill (skill_id) or years of experience (min_years).
 *
 * @property int $id
 * @property int $job_posting_id
 * @property int|null $skill_id
 * @property RequirementKind $kind
 * @property int|null $min_years
 * @property string $raw_text
 * @property string $extractor_version
 * @property-read Skill|null $skill
 */
#[Fillable(['job_posting_id', 'skill_id', 'kind', 'min_years', 'raw_text', 'extractor_version'])]
class JobRequirement extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => RequirementKind::class,
        ];
    }

    /**
     * @return BelongsTo<JobPosting, $this>
     */
    public function posting(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class, 'job_posting_id');
    }

    /**
     * @return BelongsTo<Skill, $this>
     */
    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }
}
