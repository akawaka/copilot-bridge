<?php

declare(strict_types=1);

namespace Akawaka\Bridge\Copilot\Tests;

use PHPUnit\Framework\TestCase;
use Akawaka\Bridge\Copilot\ModelCapabilitiesResolver;
use Symfony\AI\Platform\Capability;

final class ModelCapabilitiesResolverTest extends TestCase
{
    public function testResolveReturnsCorrectCapabilitiesForGptModel(): void
    {
        $resolver = new ModelCapabilitiesResolver();
        $result = $resolver->resolve('gpt-5-mini');
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('capabilities', $result);
        $this->assertArrayHasKey('options', $result);
        
        $capabilities = $result['capabilities'];
        $this->assertContains(Capability::INPUT_MESSAGES, $capabilities);
        $this->assertContains(Capability::OUTPUT_TEXT, $capabilities);
    }

    public function testListModelsReturnsArray(): void
    {
        $resolver = new ModelCapabilitiesResolver();
        $models = $resolver->listModels();
        
        $this->assertIsArray($models);
        $this->assertNotEmpty($models);
        
        $firstModel = $models[0];
        $this->assertArrayHasKey('name', $firstModel);
        $this->assertArrayHasKey('provider', $firstModel);
        $this->assertArrayHasKey('capabilities', $firstModel);
        $this->assertArrayHasKey('options', $firstModel);
    }
}