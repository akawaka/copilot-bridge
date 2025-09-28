<?php

declare(strict_types=1);

namespace Akawaka\Bridge\Copilot;

use Akawaka\Bridge\Copilot\Chat\ModelClient;
use Akawaka\Bridge\Copilot\Chat\ResultConverter;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Platform;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class PlatformFactory
{
    public static function create(
        HttpClientInterface $httpClient,
        string $chatCompletionsEndpoint,
        string $modelResponsesEndpoint,
        ?Contract $contract = null,
    ): Platform
    {
        return new Platform(
            [new ModelClient($httpClient, $chatCompletionsEndpoint, $modelResponsesEndpoint)],
            [new ResultConverter()],
            $contract ?? Contract::create(),
        );
    }
}
