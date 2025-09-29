<?php

declare(strict_types=1);

namespace Akawaka\Bridge\Copilot\Tests;

use Akawaka\Bridge\Copilot\PlatformFactory;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Platform;
use Symfony\Component\HttpClient\HttpClient;

final class PlatformFactoryTest extends TestCase
{
    public function testPlatformFactoryCanCreatePlatform(): void
    {
        $httpClient = HttpClient::create();
        
        $platform = PlatformFactory::create(
            $httpClient,
            'https://api.githubcopilot.com/chat/completions',
            'https://copilot-proxy.githubusercontent.com/models'
        );
        
        $this->assertInstanceOf(Platform::class, $platform);
    }

    public function testPlatformFactoryCanCreatePlatformWithCustomContract(): void
    {
        $httpClient = HttpClient::create();
        $contract = Contract::create();
        
        $platform = PlatformFactory::create(
            $httpClient,
            'https://api.githubcopilot.com/chat/completions',
            'https://copilot-proxy.githubusercontent.com/models',
            $contract
        );
        
        $this->assertInstanceOf(Platform::class, $platform);
    }

    public function testPlatformFactoryCreatedPlatformHasModelCatalog(): void
    {
        $httpClient = HttpClient::create();
        
        $platform = PlatformFactory::create(
            $httpClient,
            'https://api.githubcopilot.com/chat/completions',
            'https://copilot-proxy.githubusercontent.com/models'
        );
        
        $modelCatalog = $platform->getModelCatalog();
        $this->assertNotNull($modelCatalog);
        
        // Test that it can get a model (DynamicModelCatalog should work with any model name)
        $model = $modelCatalog->getModel('test-model');
        $this->assertNotNull($model);
        $this->assertEquals('test-model', $model->getName());
    }
}