<?php

namespace App\Services\Ai\Contracts;

use App\Services\Ai\Data\AiRequest;
use App\Services\Ai\Data\AiResponse;
use App\Services\Ai\Exceptions\AiProviderException;

interface AiProvider
{
    /**
     * Config key, e.g. "gemini".
     */
    public function name(): string;

    public function model(): string;

    /**
     * Ask for a JSON object response.
     *
     * @throws AiProviderException with a classified outcome on any failure
     */
    public function complete(AiRequest $request, int $maxTokens): AiResponse;
}
