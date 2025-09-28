<?php

declare(strict_types=1);

namespace Akawaka\Bridge\Copilot;

use Symfony\AI\Platform\Model;

/**
 * @author Franck Matsos <github@franck.matsos.fr>
 */
final class Copilot extends Model
{
    public const CLAUDE_35_SONNET = 'claude-3.5-sonnet';
    public const CLAUDE_37_SONNET = 'claude-3.7-sonnet';
    public const CLAUDE_37_SONNET_THOUGHT = 'claude-3.7-sonnet-thought';
    public const CLAUDE_OPUS_4 = 'claude-opus-4';
    public const CLAUDE_OPUS_41 = 'claude-opus-41';
    public const CLAUDE_SONNET_4 = 'claude-sonnet-4';
    public const GEMINI_20_FLASH = 'gemini-2.0-flash-001';
    public const GEMINI_25_PRO = 'gemini-2.5-pro';
    public const GPT_41 = 'gpt-4.1';
    public const GPT_4O = 'gpt-4o';
    public const GPT_5 = 'gpt-5';
    public const GPT_5_CODEX = 'gpt-5-codex';
    public const GPT_5_MINI = 'gpt-5-mini';
    public const GROK_CODE_FAST_1 = 'grok-code-fast-1';
    public const O3 = 'o3';
    public const O3_MINI = 'o3-mini';
    public const O4_MINI = 'o4-mini';

    /**
     * @param array<string, mixed> $options The default options for the model usage
     */
    public function __construct(
        string $name = self::GPT_5_MINI,
        array $options = ['temperature' => 1.0],
        ?ModelCapabilitiesResolverInterface $resolver = null
    ) {
        $resolver ??= new ModelCapabilitiesResolver();
        $resolved = $resolver->resolve($name);

        $mergedOptions = array_merge($resolved['options'], $options);
        
        parent::__construct($name, $resolved['capabilities'], $mergedOptions);
    }
}