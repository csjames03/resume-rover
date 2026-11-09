<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AiProviderEvent;
use App\Services\Ai\AiAvailability;
use App\Services\Ai\ProviderCooldowns;
use App\Services\Ai\ProviderRegistry;
use App\Services\Ai\Quota\ProviderQuotas;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only status of the AI providers: configured, benched, recent calls.
 */
class AiProviderController extends Controller
{
    public function show(Request $request, ProviderRegistry $registry, ProviderCooldowns $cooldowns, ProviderQuotas $quotas, AiAvailability $availability): Response
    {
        $providers = array_map(fn (array $provider) => [
            ...$provider,
            'cooling_down_until' => $cooldowns->isCoolingDown($provider['name'])
                ? $cooldowns->until($provider['name'])?->toIso8601String()
                : null,
            'quota' => $quotas->get($provider['name'])?->toArray(),
        ], $registry->describe());

        return Inertia::render('settings/ai-providers', [
            'providers' => $providers,
            'priority' => array_values((array) config('ai.priority')),
            'usage' => $availability->status($request->user()),
            'events' => AiProviderEvent::query()->latest('id')->limit(20)->get()->map(fn (AiProviderEvent $e) => [
                'id' => $e->id,
                'provider' => $e->provider,
                'model' => $e->model,
                'task' => $e->task->label(),
                'outcome' => $e->outcome->value,
                'latency_ms' => $e->latency_ms,
                'error_message' => $e->error_message,
                'created_at' => $e->created_at->toIso8601String(),
            ]),
        ]);
    }
}
