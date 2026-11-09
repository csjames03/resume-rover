<?php

namespace App\Models;

use App\Enums\ApplicationEventType;
use App\Enums\ApplicationStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only history entry for an application.
 *
 * @property int $id
 * @property int $application_id
 * @property ApplicationEventType $type
 * @property ApplicationStatus|null $from_status
 * @property ApplicationStatus|null $to_status
 * @property array<string, mixed>|null $meta
 * @property CarbonImmutable $created_at
 */
#[Fillable(['application_id', 'type', 'from_status', 'to_status', 'meta'])]
class ApplicationEvent extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ApplicationEventType::class,
            'from_status' => ApplicationStatus::class,
            'to_status' => ApplicationStatus::class,
            'meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Application, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}
