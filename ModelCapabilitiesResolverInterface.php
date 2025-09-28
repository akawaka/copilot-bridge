<?php

declare(strict_types=1);

namespace Akawaka\Bridge\Copilot;

interface ModelCapabilitiesResolverInterface
{
    public function resolve(string $modelName): array;

    /**
     * @return array<int, array{
     *     name: string,
     *     provider: string,
     *     capabilities: array<int, \Symfony\AI\Platform\Capability>,
     *     options: array<string, mixed>
     * }>
     */
    public function listModels(): array;
}