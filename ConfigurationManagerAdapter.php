<?php

declare(strict_types=1);

namespace Akawaka\Bridge\Copilot;

use Akawacode\Configuration\ConfigurationManagerInterface as CoreConfigurationManagerInterface;
use Akawaka\Bridge\Copilot\Contract\ConfigurationManagerInterface;

final readonly class ConfigurationManagerAdapter implements ConfigurationManagerInterface
{
    public function __construct(
        private CoreConfigurationManagerInterface $configManager
    ) {
    }

    public function getProviderConfig(string $provider): array
    {
        return $this->configManager->getProviderConfig($provider);
    }

    public function setProviderConfig(string $provider, array $config): void
    {
        $this->configManager->setProviderConfig($provider, $config);
    }

    public function removeProviderConfig(string $provider): void
    {
        $this->configManager->removeProviderConfig($provider);
    }

    public function getApiKey(string $provider): ?string
    {
        return $this->configManager->getApiKey($provider);
    }

    public function setApiKey(string $provider, string $apiKey): void
    {
        $this->configManager->setApiKey($provider, $apiKey);
    }

    public function getAuthTokens(string $provider): array
    {
        return $this->configManager->getAuthTokens($provider);
    }

    public function setAuthTokens(string $provider, array $tokens): void
    {
        $this->configManager->setAuthTokens($provider, $tokens);
    }

    public function isProviderEnabled(string $provider): bool
    {
        return $this->configManager->isProviderEnabled($provider);
    }

    public function setProviderEnabled(string $provider, bool $enabled): void
    {
        $this->configManager->setProviderEnabled($provider, $enabled);
    }
}
