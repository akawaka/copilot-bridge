<?php

declare(strict_types=1);

namespace Akawaka\Bridge\Copilot\Tests;

use Akawaka\Bridge\Copilot\DependencyInjection\CopilotExtension;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Platform;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class DependencyInjectionTest extends TestCase
{
    public function testExtensionRegistersPlatformService(): void
    {
        $container = new ContainerBuilder();
        $extension = new CopilotExtension();
        
        $extension->load([], $container);
        
        $this->assertTrue($container->hasDefinition('copilot.platform'));
        
        $definition = $container->getDefinition('copilot.platform');
        $this->assertEquals(Platform::class, $definition->getClass());
    }

    public function testExtensionRegistersPlatformFactory(): void
    {
        $container = new ContainerBuilder();
        $extension = new CopilotExtension();
        
        $extension->load([], $container);
        
        $this->assertTrue($container->hasDefinition('copilot.platform_factory'));
    }
}