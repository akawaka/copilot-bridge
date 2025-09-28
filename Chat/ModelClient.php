<?php

declare(strict_types=1);

namespace Akawaka\Bridge\Copilot\Chat;

use Akawaka\Bridge\Copilot\Copilot;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ModelClient implements ModelClientInterface
{
    private const ACCESS_TOKEN_OPTION = 'copilot_access_token';
    private const HTTP_CLIENT_OPTION = 'http_client';

    private EventSourceHttpClient $httpClient;

    public function __construct(
        HttpClientInterface $httpClient,
        #[Autowire(param: 'copilot.chat_completions_endpoint')]
        private readonly string $chatCompletionsEndpoint,
        #[Autowire(param: 'copilot.model_responses_endpoint')]
        private readonly string $modelResponsesEndpoint,
    ) {
        $this->httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Copilot;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $options
     *
     * @throws AuthenticationException
     * @throws RuntimeException
     * @throws TransportExceptionInterface
     */
    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        if (!\is_array($payload)) {
            throw new RuntimeException('GitHub Copilot expects an array payload.');
        }

        $accessToken = $options[self::ACCESS_TOKEN_OPTION] ?? null;
        if (null === $accessToken || '' === $accessToken) {
            throw new AuthenticationException('GitHub Copilot is not authenticated. Run authentication first.');
        }

        unset($options[self::ACCESS_TOKEN_OPTION]);

        $httpClientOptions = $options[self::HTTP_CLIENT_OPTION] ?? [];
        if (!\is_array($httpClientOptions)) {
            throw new RuntimeException('GitHub Copilot HTTP client options must be provided as an array.');
        }

        unset($options[self::HTTP_CLIENT_OPTION]);

        if (!isset($httpClientOptions['timeout'])) {
            $httpClientOptions['timeout'] = 30;
        }

        $requestOptions = array_merge($httpClientOptions, [
            'headers' => $this->createHeaders($accessToken),
            'json' => array_merge($payload, $options),
        ]);

        $endpoint = $this->chatCompletionsEndpoint;

        if ($model->getName() === Copilot::GPT_5_CODEX) {
            $endpoint = $this->modelResponsesEndpoint;;
        }

        return new RawHttpResult($this->httpClient->request('POST', $endpoint, $requestOptions));
    }

    private function createHeaders(string $accessToken): array
    {
        return [
            'Authorization' => sprintf('Bearer %s', $accessToken),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'User-Agent' => 'GitHubCopilotChat/0.26.7',
            'Editor-Version' => 'vscode/1.104.1',
            'Editor-Plugin-Version' => 'copilot-chat/0.26.7',
        ];
    }
}