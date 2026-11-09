<?php

namespace App\Services\Ai;

use App\Enums\AiTask;
use App\Models\User;
use App\Services\Ai\Exceptions\AiLimitReached;
use App\Services\Ai\Quota\UserAiAllowance;

/**
 * Whether a user may start an AI action now: their own daily allowance, plus
 * the shared providers' rate limits. Checked before work is queued, so users
 * get an honest answer at the button instead of a failed job later.
 */
class AiAvailability
{
    // Short waits (Groq's per-minute token window) are absorbed by the queue job retrying.
    private const QUEUE_ABSORBS_SECONDS = 60;

    public function __construct(
        private readonly AiGateway $gateway,
        private readonly UserAiAllowance $allowance,
    ) {}

    /**
     * What the UI shows next to AI buttons.
     *
     * @return array{enabled: bool, remaining: int, limit: int, resets_in: int, busy_for: int}
     */
    public function status(User $user, AiTask $task = AiTask::TailorResume): array
    {
        $wait = $this->gateway->secondsUntilAvailable($task);

        return [
            'enabled' => $wait !== null,
            'remaining' => $this->allowance->remaining($user),
            'limit' => $this->allowance->limit(),
            'resets_in' => $this->allowance->resetsIn($user),
            'busy_for' => $wait ?? 0,
        ];
    }

    /**
     * Takes one action from the user's allowance, or explains why not.
     *
     * @throws AiLimitReached
     */
    public function claim(User $user, AiTask $task): void
    {
        $wait = $this->gateway->secondsUntilAvailable($task);

        if ($wait === null) {
            throw AiLimitReached::notConfigured();
        }

        if (! $this->allowance->hasRemaining($user)) {
            throw AiLimitReached::dailyAllowanceUsed($this->allowance->limit(), $this->allowance->resetsIn($user));
        }

        if ($wait > self::QUEUE_ABSORBS_SECONDS) {
            throw AiLimitReached::providersBusy($wait);
        }

        $this->allowance->consume($user);
    }

    /**
     * Like claim(), for features with a non-AI fallback: true means go ahead with AI.
     */
    public function tryClaim(User $user, AiTask $task): bool
    {
        try {
            $this->claim($user, $task);

            return true;
        } catch (AiLimitReached) {
            return false;
        }
    }
}
