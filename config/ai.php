<?php

/*
|--------------------------------------------------------------------------
| AI providers
|--------------------------------------------------------------------------
|
| Every provider here speaks the OpenAI-compatible chat completions API, so
| one adapter covers all of them. A provider is only used when its API key
| is set. Free tiers, model names and limits change often: check each
| provider's current terms and override the model through the env file.
|
*/

return [

    'providers' => [
        'gemini' => [
            'label' => 'Google Gemini',
            'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta/openai'),
            'api_key' => env('GEMINI_API_KEY'),
            'model' => env('GEMINI_MODEL', 'gemini-3.5-flash'),
            // Gemini 3 models "think" before answering; low keeps them fast. Null = provider default.
            'reasoning_effort' => env('GEMINI_REASONING_EFFORT', 'low'),
        ],
        'groq' => [
            'label' => 'Groq',
            'base_url' => env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'),
            'api_key' => env('GROQ_API_KEY'),
            'model' => env('GROQ_MODEL', 'llama-3.3-70b-versatile'),
        ],
        'openrouter' => [
            'label' => 'OpenRouter',
            'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
            'api_key' => env('OPENROUTER_API_KEY'),
            'model' => env('OPENROUTER_MODEL', 'meta-llama/llama-3.3-70b-instruct:free'),
        ],
    ],

    // Default failover order. The first healthy provider wins.
    'priority' => array_filter(array_map('trim', explode(',', (string) env('AI_PROVIDER_PRIORITY', 'gemini,groq,openrouter')))),

    // Per-task settings. "providers" overrides the default order for that task.
    'tasks' => [
        'parse_resume' => ['max_tokens' => 4000, 'providers' => null],
        'explain_match' => ['max_tokens' => 700, 'providers' => null],
        'tailor_resume' => ['max_tokens' => 4000, 'providers' => null],
        'draft_email' => ['max_tokens' => 1200, 'providers' => null],
    ],

    'timeout_seconds' => (int) env('AI_TIMEOUT_SECONDS', 60),

    // AI actions (explanations, tailored drafts, email drafts) each user may start per rolling
    // 24 hours. Everyone shares the app's API key, so this keeps one user from using up a
    // free tier. Always at least 1.
    'user_daily_limit' => (int) env('AI_USER_DAILY_LIMIT', 30),

    // Benching a failing provider stops us from hammering a rate limit.
    'cooldown' => [
        'base_seconds' => 30,
        'max_seconds' => 3600,
        'auth_error_seconds' => 3600,
    ],

];
