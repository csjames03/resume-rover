<?php

namespace App\Services\Ai;

use App\Enums\AiTask;
use App\Services\Ai\Contracts\AiProvider;
use App\Services\Ai\Providers\OpenAiCompatibleProvider;

/**
 * Builds provider adapters from config/ai.php and orders them per task.
 */
class ProviderRegistry
{
    /**
     * Providers that have an API key, in the order this task should try them.
     *
     * @return list<AiProvider>
     */
    public function forTask(AiTask $task): array
    {
        $order = config("ai.tasks.{$task->value}.providers") ?? config('ai.priority');

        $providers = [];

        foreach ((array) $order as $name) {
            $provider = $this->make((string) $name);

            if ($provider !== null) {
                $providers[] = $provider;
            }
        }

        return $providers;
    }

    /**
     * Every provider in config, configured or not, for the status page.
     *
     * @return list<array{name: string, label: string, model: string, configured: bool}>
     */
    public function describe(): array
    {
        $rows = [];

        foreach ((array) config('ai.providers') as $name => $config) {
            $rows[] = [
                'name' => (string) $name,
                'label' => (string) $config['label'],
                'model' => (string) $config['model'],
                'configured' => filled($config['api_key']),
            ];
        }

        return $rows;
    }

    public function make(string $name): ?AiProvider
    {
        $config = config("ai.providers.{$name}");

        if (! is_array($config) || blank($config['api_key'] ?? null)) {
            return null;
        }

        return new OpenAiCompatibleProvider(
            name: $name,
            baseUrl: rtrim((string) $config['base_url'], '/').'/',
            apiKey: (string) $config['api_key'],
            model: (string) $config['model'],
            timeoutSeconds: (int) config('ai.timeout_seconds'),
            reasoningEffort: filled($config['reasoning_effort'] ?? null) ? (string) $config['reasoning_effort'] : null,
        );
    }
}
