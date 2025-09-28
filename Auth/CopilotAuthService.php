<?php

declare(strict_types=1);

namespace Akawaka\Bridge\Copilot\Auth;

use Akawaka\Bridge\Copilot\Contract\ConfigurationManagerInterface;
use Akawaka\Bridge\Copilot\Exception\AuthenticationException;
use Akawaka\Bridge\Copilot\Exception\TokenException;
use Akawaka\Bridge\Copilot\Exception\DeviceCodeException;
use Akawaka\Bridge\Copilot\Exception\CopilotException;
use Akawaka\Bridge\Copilot\Exception\TokenExchangeException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class CopilotAuthService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private ConfigurationManagerInterface $configManager,
        private LoggerInterface $logger,
        #[Autowire(param: 'copilot.client_id')]
        private string $clientId,
        #[Autowire(param: 'copilot.device_code_url')]
        private string $deviceCodeUrl,
        #[Autowire(param: 'copilot.access_token_url')]
        private string $accessTokenUrl,
        #[Autowire(param: 'copilot.api_key_url')]
        private string $apiKeyUrl,

    ) {
    }

    /**
     * @return array{
     *     device: string,
     *     user: string,
     *     verification: string,
     *     interval: int,
     *     expiry: int
     * }
     * @throws CopilotException
     */
    public function authorize(): array
    {
        try {
            $response = $this->httpClient->request('POST', $this->deviceCodeUrl, [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'GitHubCopilotChat/0.26.7',
                ],
                'json' => [
                    'client_id' => $this->clientId,
                    'scope' => 'read:user',
                ],
            ]);

            $deviceData = $response->toArray();

            return [
                'device' => $deviceData['device_code'],
                'user' => $deviceData['user_code'],
                'verification' => $deviceData['verification_uri'],
                'interval' => $deviceData['interval'] ?? 5,
                'expiry' => $deviceData['expires_in'],
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to authorize GitHub Copilot device', [
                'error' => $e->getMessage(),
            ]);
            throw new DeviceCodeException('Failed to get device code');
        }
    }

    /**
     * @throws CopilotException
     */
    public function poll(string $deviceCode): string
    {
        try {
            $response = $this->httpClient->request('POST', $this->accessTokenUrl, [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'GitHubCopilotChat/0.26.7',
                ],
                'json' => [
                    'client_id' => $this->clientId,
                    'device_code' => $deviceCode,
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:device_code',
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                return 'failed';
            }

            $data = $response->toArray();

            if (isset($data['access_token'])) {
                $this->configManager->setAuthTokens('copilot', [
                    'type' => 'oauth',
                    'refresh' => $data['access_token'],
                    'access' => '',
                    'expires' => 0,
                ]);
                return 'complete';
            }

            if (isset($data['error'])) {
                if ($data['error'] === 'authorization_pending') {
                    return 'pending';
                }
                return 'failed';
            }

            return 'pending';
        } catch (\Exception $e) {
            $this->logger->error('Failed to poll for GitHub Copilot access token', [
                'device_code' => $deviceCode,
                'error' => $e->getMessage(),
            ]);
            throw new TokenExchangeException($e->getMessage());
        }
    }

    /**
     * @throws CopilotException
     */
    public function getAccessToken(): ?string
    {
        try {
            $info = $this->configManager->getAuthTokens('copilot');

            if (empty($info) || ($info['type'] ?? '') !== 'oauth') {
                return null;
            }

            // Return existing token if still valid
            if (!empty($info['access']) && ($info['expires'] ?? 0) > time() * 1000) {
                return $info['access'];
            }

            // Get new Copilot API token
            $response = $this->httpClient->request('GET', $this->apiKeyUrl, [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => sprintf('Bearer %s', $info['refresh']),
                    'User-Agent' => 'GitHubCopilotChat/0.26.7',
                    'Editor-Version' => 'vscode/1.99.3',
                    'Editor-Plugin-Version' => 'copilot-chat/0.26.7',
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                throw new AuthenticationException('Failed to get Copilot API token');
            }

            $tokenData = $response->toArray();

            // Store the Copilot API token
            $this->configManager->setAuthTokens('copilot', [
                'type' => 'oauth',
                'refresh' => $info['refresh'],
                'access' => $tokenData['token'],
                'expires' => $tokenData['expires_at'] * 1000,
            ]);

            return $tokenData['token'];
        } catch (\Exception $e) {
            $this->logger->error('Failed to get GitHub Copilot access token', [
                'error' => $e->getMessage(),
            ]);
            throw new TokenException($e->getMessage());
        }
    }

    /**
     * Remove stored authentication tokens.
     * 
     * @throws CopilotException
     */
    public function removeTokens(): void
    {
        try {
            $this->configManager->removeProviderConfig('copilot');
        } catch (\Exception $e) {
            $this->logger->error('Failed to remove GitHub Copilot tokens', [
                'error' => $e->getMessage(),
            ]);
            throw new CopilotException($e->getMessage());
        }
    }
}