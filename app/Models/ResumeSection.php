<?php

namespace App\Models;

use App\Enums\SectionType;
use Carbon\CarbonImmutable;
use Database\Factories\ResumeSectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $resume_id
 * @property SectionType $type
 * @property int $position
 * @property string|null $title
 * @property string|null $organization
 * @property string|null $location
 * @property CarbonImmutable|null $start_date
 * @property CarbonImmutable|null $end_date
 * @property bool $is_current
 * @property string|null $description
 * @property string|null $source_text
 * @property float|null $confidence
 * @property bool $is_user_edited
 * @property-read Resume $resume
 */
#[Fillable([
    'resume_id', 'type', 'position', 'title', 'organization', 'location', 'start_date', 'end_date',
    'is_current', 'description', 'source_text', 'confidence', 'is_user_edited',
])]
class ResumeSection extends Model
{
    /** @use HasFactory<ResumeSectionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => SectionType::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'is_current' => 'boolean',
            'confidence' => 'float',
            'is_user_edited' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Resume, $this>
     */
    public function resume(): BelongsTo
    {
        return $this->belongsTo(Resume::class);
    }

    /**
     * @return HasMany<ResumeBullet, $this>
     */
    public function bullets(): HasMany
    {
        return $this->hasMany(ResumeBullet::class)->orderBy('position');
    }
}
