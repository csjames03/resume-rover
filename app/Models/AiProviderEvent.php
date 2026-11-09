<?php

namespace App\Models;

use App\Enums\AiOutcome;
use App\Enums\AiTask;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One call to an AI provider. Metadata only: never prompts or outputs.
 *
 * @property int $id
 * @property string $provider
 * @property string $model
 * @property AiTask $task
 * @property AiOutcome $outcome
 * @property int|null $http_status
 * @property int|null $latency_ms
 * @property int|null $input_tokens
 * @property int|null $output_tokens
 * @property string|null $error_message
 * @property CarbonImmutable $created_at
 */
#[Fillable([
    'provider', 'model', 'task', 'outcome', 'http_status', 'latency_ms',
    'input_tokens', 'output_tokens', 'error_message',
])]
class AiProviderEvent extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'task' => AiTask::class,
            'outcome' => AiOutcome::class,
        ];
    }
}
