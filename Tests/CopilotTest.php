<?php

declare(strict_types=1);

namespace Akawaka\Bridge\Copilot\Tests;

use PHPUnit\Framework\TestCase;
use Akawaka\Bridge\Copilot\Copilot;

final class CopilotTest extends TestCase
{
    public function testCopilotCanBeInstantiated(): void
    {
        $model = new Copilot();
        
        $this->assertInstanceOf(Copilot::class, $model);
        $this->assertEquals(Copilot::GPT_5_MINI, $model->getName());
    }

    public function testCopilotWithCustomName(): void
    {
        $model = new Copilot(Copilot::GPT_4O);
        
        $this->assertEquals(Copilot::GPT_4O, $model->getName());
    }

    public function testCopilotWithCustomOptions(): void
    {
        $customOptions = ['temperature' => 0.5, 'max_tokens' => 2000];
        $model = new Copilot(Copilot::GPT_5_MINI, $customOptions);
        
        $this->assertEquals(Copilot::GPT_5_MINI, $model->getName());
        $this->assertEquals(0.5, $model->getOptions()['temperature']);
    }
}