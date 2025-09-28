<?php

declare(strict_types=1);

namespace Akawaka\Bridge\Copilot;

use Akawaka\Bridge\Copilot\Config\CopilotModelsConfiguration;
use ReflectionClass;
use Symfony\AI\Platform\Capability;
use Symfony\Component\Config\Definition\Processor;

final class ModelCapabilitiesResolver implements ModelCapabilitiesResolverInterface
{
    private const CLAUDE_MODEL = 'claude';
    private const GEMINI_MODEL = 'gemini';
    private const GPT_MODEL = 'gpt';

    private array $config;

    private const MODEL_GROUPS = [
        // Claude models
        Copilot::CLAUDE_35_SONNET => self::CLAUDE_MODEL,
        Copilot::CLAUDE_37_SONNET => self::CLAUDE_MODEL,
        Copilot::CLAUDE_37_SONNET_THOUGHT => self::CLAUDE_MODEL,
        Copilot::CLAUDE_OPUS_4 => self::CLAUDE_MODEL,
        Copilot::CLAUDE_OPUS_41 => self::CLAUDE_MODEL,
        Copilot::CLAUDE_SONNET_4 => self::CLAUDE_MODEL,
        
        // Gemini models
        Copilot::GEMINI_20_FLASH => self::GEMINI_MODEL,
        Copilot::GEMINI_25_PRO => self::GEMINI_MODEL,
        
        // GPT models
        Copilot::GPT_41 => self::GPT_MODEL,
        Copilot::GPT_4O => self::GPT_MODEL,
        Copilot::GPT_5 => self::GPT_MODEL,
        Copilot::GPT_5_CODEX => self::GPT_MODEL,
        Copilot::GPT_5_MINI => self::GPT_MODEL,
        Copilot::O3 => self::GPT_MODEL,
        Copilot::O3_MINI => self::GPT_MODEL,
        Copilot::O4_MINI => self::GPT_MODEL,
    ];

    private const BASE_CAPABILITIES = [
        Capability::INPUT_MESSAGES,
        Capability::INPUT_IMAGE,
        Capability::OUTPUT_STREAMING,
        Capability::TOOL_CALLING,
    ];

    private const PROVIDER_CAPABILITIES = [
        self::CLAUDE_MODEL => [
            Capability::OUTPUT_TEXT
        ],
        self::GEMINI_MODEL => [
            Capability::INPUT_AUDIO,
            Capability::INPUT_PDF,
            Capability::OUTPUT_STRUCTURED,
        ],
        self::GPT_MODEL => [
            Capability::OUTPUT_TEXT,
            Capability::OUTPUT_STRUCTURED,
        ],
    ];

    private const PROVIDER_DEFAULTS = [
        self::CLAUDE_MODEL => ['max_tokens' => 1000],
        self::GEMINI_MODEL => [],
        self::GPT_MODEL => [],
    ];

    public function __construct(?array $config = null)
    {
        if (null !== $config) {
            $processor = new Processor();
            $this->config = $processor->processConfiguration(new CopilotModelsConfiguration(), [$config]);
        } else {
            $this->config = [];
        }
    }

    public function resolve(string $modelName): array
    {
        if ($this->config !== []) {
            return $this->resolveFromConfig($modelName);
        }

        return $this->resolveFromConstants($modelName);
    }

    /**
     * @return array<int, array{name: string, provider: string, capabilities: array<int, Capability>, options: array<string, mixed>}>
     */
    public function listModels(): array
    {
        if ($this->config !== []) {
            return $this->listModelsFromConfig();
        }

        return $this->listModelsFromConstants();
    }
    
    private function resolveFromConfig(string $modelName): array
    {
        $provider = null;
        foreach ($this->config['providers'] ?? [] as $providerName => $providerConfig) {
            $models = $providerConfig['models'] ?? [];
            if (in_array($modelName, $models, true)) {
                $provider = $providerName;
                break;
            }
        }

        if (!$provider) {
            throw new \InvalidArgumentException("Unknown model: $modelName");
        }

        $defaultsConfig = $this->config['_defaults'] ?? [];
        $providerConfig = $this->config['providers'][$provider];

        $baseCapabilities = array_map(
            fn($cap) => $this->mapCapabilityStringToConstant($cap),
            $defaultsConfig['capabilities'] ?? []
        );

        $providerCapabilities = array_map(
            fn($cap) => $this->mapCapabilityStringToConstant($cap),
            $providerConfig['capabilities'] ?? []
        );
        
        $capabilities = array_merge($baseCapabilities, $providerCapabilities);
        
        return [
            'capabilities' => array_values(array_unique($capabilities, SORT_REGULAR)),
            'options' => $providerConfig['options'] ?? [],
        ];
    }
    
    private function resolveFromConstants(string $modelName): array
    {
        $provider = self::MODEL_GROUPS[$modelName] ?? 'unknown';
        
        $capabilities = self::BASE_CAPABILITIES;
        if (isset(self::PROVIDER_CAPABILITIES[$provider])) {
            $capabilities = array_merge($capabilities, self::PROVIDER_CAPABILITIES[$provider]);
        }
        
        $defaultOptions = self::PROVIDER_DEFAULTS[$provider] ?? [];
        
        return [
            'capabilities' => array_values(array_unique($capabilities, SORT_REGULAR)),
            'options' => $defaultOptions,
        ];
    }
    
    private function mapCapabilityStringToConstant(string $capabilityString): Capability
    {
        $enumValue = str_replace('_', '-', $capabilityString);

        try {
            return Capability::from($enumValue);
        } catch (\ValueError $e) {
            throw new \InvalidArgumentException("Unknown capability: $capabilityString");
        }
    }

    /**
     * @return array<int, array{name: string, provider: string, capabilities: array<int, Capability>, options: array<string, mixed>}>
     */
    private function listModelsFromConfig(): array
    {
        $models = [];
        $defaultsConfig = $this->config['_defaults'] ?? [];

        $baseCapabilities = array_map(
            fn(string $capability) => $this->mapCapabilityStringToConstant($capability),
            $defaultsConfig['capabilities'] ?? []
        );

        foreach ($this->config['providers'] ?? [] as $providerName => $providerConfig) {
            $providerCapabilities = array_map(
                fn(string $capability) => $this->mapCapabilityStringToConstant($capability),
                $providerConfig['capabilities'] ?? []
            );

            $capabilities = array_values(array_unique(
                array_merge($baseCapabilities, $providerCapabilities),
                SORT_REGULAR
            ));

            $options = $providerConfig['options'] ?? [];

            foreach ($providerConfig['models'] ?? [] as $modelName) {
                $models[] = [
                    'name' => $modelName,
                    'provider' => $providerName,
                    'capabilities' => $capabilities,
                    'options' => $options,
                ];
            }
        }

        return $this->sortModels($models);
    }

    /**
     * @return array<int, array{name: string, provider: string, capabilities: array<int, Capability>, options: array<string, mixed>}>
     */
    private function listModelsFromConstants(): array
    {
        $reflection = new ReflectionClass(Copilot::class);
        $models = [];

        foreach ($reflection->getConstants() as $value) {
            if (!is_string($value)) {
                continue;
            }

            $provider = self::MODEL_GROUPS[$value] ?? 'unknown';
            $resolved = $this->resolveFromConstants($value);

            $models[] = [
                'name' => $value,
                'provider' => $provider,
                'capabilities' => $resolved['capabilities'],
                'options' => $resolved['options'],
            ];
        }

        return $this->sortModels($models);
    }

    /**
     * @param array<int, array{name: string, provider: string, capabilities: array<int, Capability>, options: array<string, mixed>}> $models
     *
     * @return array<int, array{name: string, provider: string, capabilities: array<int, Capability>, options: array<string, mixed>}>
     */
    private function sortModels(array $models): array
    {
        usort(
            $models,
            static function (array $left, array $right): int {
                $providerComparison = strcmp($left['provider'], $right['provider']);

                if ($providerComparison !== 0) {
                    return $providerComparison;
                }

                return strcmp($left['name'], $right['name']);
            }
        );

        return $models;
    }
}