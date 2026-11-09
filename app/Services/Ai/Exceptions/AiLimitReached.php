<?php

namespace App\Services\Ai\Exceptions;

use DomainException;

/**
 * The user can't start an AI action right now. The message is written for the user.
 */
class AiLimitReached extends DomainException
{
    public static function dailyAllowanceUsed(int $limit, int $resetsInSeconds): self
    {
        return new self("You've used all {$limit} AI actions for today. More become available in ".AiUnavailableException::humanize($resetsInSeconds).'.');
    }

    public static function providersBusy(int $waitSeconds): self
    {
        return new self('The free AI tier is at its limit right now. Try again in '.AiUnavailableException::humanize($waitSeconds).'.');
    }

    public static function notConfigured(): self
    {
        return new self('AI features need an API key. Add GROQ_API_KEY (or another provider) to the .env file.');
    }
}
