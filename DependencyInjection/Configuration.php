<?php

declare(strict_types=1);

namespace Akawaka\Bridge\Copilot\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('copilot');

        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('client_id')
                    ->defaultValue('Iv1.b507a08c87ecfe98')
                    ->info('GitHub Copilot client ID')
                ->end()
                ->scalarNode('device_code_url')
                    ->defaultValue('https://github.com/login/device/code')
                    ->info('GitHub device code URL')
                ->end()
                ->scalarNode('access_token_url')
                    ->defaultValue('https://github.com/login/oauth/access_token')
                    ->info('GitHub access token URL')
                ->end()
                ->scalarNode('api_key_url')
                    ->defaultValue('https://api.github.com/copilot_internal/v2/token')
                    ->info('GitHub Copilot API key URL')
                ->end()
                ->scalarNode('chat_completions_endpoint')
                    ->defaultValue('https://api.githubcopilot.com/chat/completions')
                    ->info('GitHub Copilot chat completions endpoint')
                ->end()
                ->scalarNode('model_responses_endpoint')
                    ->defaultValue('https://api.githubcopilot.com/responses')
                    ->info('GitHub Copilot model responses endpoint')
                ->end()
                ->integerNode('auth_token_expiration')
                    ->defaultValue(90)
                    ->info('Authentication token expiration in days')
                ->end()
                ->arrayNode('models')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->arrayNode('capabilities')
                                ->scalarPrototype()->end()
                                ->defaultValue([])
                            ->end()
                            ->arrayNode('options')
                                ->variablePrototype()->end()
                                ->defaultValue([])
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}