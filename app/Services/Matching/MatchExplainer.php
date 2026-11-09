<?php

namespace App\Services\Matching;

use App\Enums\AiTask;
use App\Enums\GenerationStatus;
use App\Models\JobMatch;
use App\Services\Ai\AiGateway;
use App\Services\Ai\Data\AiRequest;

/**
 * Turns a computed score into a short, readable explanation. The AI explains
 * the score it is given; it never decides the score.
 */
class MatchExplainer
{
    private const SYSTEM = <<<'PROMPT'
        You are a candid career coach. Explain a job-fit score that was already computed.
        Use only the strengths and gaps provided; do not assume any other skills or experience.
        Respond with a single JSON object only.
        PROMPT;

    public function __construct(private readonly AiGateway $gateway) {}

    public function explain(JobMatch $match): void
    {
        $match->loadMissing('posting');
        $posting = $match->posting;

        $points = fn (array $items) => collect($items)
            ->map(fn (array $item) => '- '.$item['label'].(isset($item['kind']) ? " ({$item['kind']})" : ''))
            ->implode("\n") ?: '- none';

        $prompt = <<<PROMPT
            Role: {$posting->title} at {$posting->company}
            Score: {$match->score}/100 ({$match->category->label()})

            Strengths:
            {$points($match->strengths)}

            Gaps:
            {$points($match->gaps)}

            Return {"summary": "2-3 sentences on why the score is what it is",
                    "advice": ["up to 3 concrete next steps, e.g. what to emphasize or learn"]}
            PROMPT;

        $result = $this->gateway->json(new AiRequest(AiTask::ExplainMatch, self::SYSTEM, $prompt, temperature: 0.4), [
            'summary' => ['required', 'string', 'max:1200'],
            'advice' => ['nullable', 'array', 'max:5'],
            'advice.*' => ['string', 'max:300'],
        ]);

        $advice = collect((array) ($result->data['advice'] ?? []))->map(fn (string $line) => "- {$line}")->implode("\n");

        $match->update([
            'explanation' => trim($result->data['summary']."\n\n".$advice),
            'explanation_status' => GenerationStatus::Ready,
            'explained_at' => now(),
        ]);
    }
}
