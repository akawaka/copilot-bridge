<?php

declare(strict_types=1);

namespace Akawaka\Bridge\Copilot\Contract;

interface ConfigurationManagerInterface
{
    /**
     * Get configuration for a specific provider.
     */
    public function getProviderConfig(string $provider): array;

    /**
     * Set configuration for a specific provider.
     */
    public function setProviderConfig(string $provider, array $config): void;

    /**
     * Remove configuration for a specific provider.
     */
    public function removeProviderConfig(string $provider): void;

    /**
     * Get API key for a provider.
     */
    public function getApiKey(string $provider): ?string;

    /**
     * Set API key for a provider.
     */
    public function setApiKey(string $provider, string $apiKey): void;

    /**
     * Get authentication tokens for a provider (like Copilot OAuth tokens).
     */
    public function getAuthTokens(string $provider): array;

    /**
     * Set authentication tokens for a provider.
     */
    public function setAuthTokens(string $provider, array $tokens): void;

    /**
     * Check if a provider is enabled.
     */
    public function isProviderEnabled(string $provider): bool;

    /**
     * Enable or disable a provider.
     */
    public function setProviderEnabled(string $provider, bool $enabled): void;
}