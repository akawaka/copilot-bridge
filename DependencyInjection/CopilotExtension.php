<?php

declare(strict_types=1);

namespace Akawaka\Bridge\Copilot\DependencyInjection;

use Akawaka\Bridge\Copilot\Auth\AuthService;
use Akawaka\Bridge\Copilot\Auth\CopilotAuthService;
use Akawaka\Bridge\Copilot\Chat\ModelClient;
use Akawaka\Bridge\Copilot\Chat\ResultConverter;
use Akawaka\Bridge\Copilot\Command\AuthCommand;
use Akawaka\Bridge\Copilot\Command\LogoutCommand;
use Akawaka\Bridge\Copilot\Command\StatusCommand;
use Akawaka\Bridge\Copilot\ModelCapabilitiesResolver;
use Akawaka\Bridge\Copilot\ModelCapabilitiesResolverInterface;
use Akawaka\Bridge\Copilot\PlatformFactory;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;

class CopilotExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        // Register the configuration as parameters
        $container->setParameter('copilot.client_id', $config['client_id']);
        $container->setParameter('copilot.device_code_url', $config['device_code_url']);
        $container->setParameter('copilot.access_token_url', $config['access_token_url']);
        $container->setParameter('copilot.api_key_url', $config['api_key_url']);
        $container->setParameter('copilot.chat_completions_endpoint', $config['chat_completions_endpoint']);
        $container->setParameter('copilot.model_responses_endpoint', $config['model_responses_endpoint']);
        $container->setParameter('copilot.auth_token_expiration', $config['auth_token_expiration']);
        $container->setParameter('copilot.models', $config['models']);

        // Register services
        $this->registerAuthServices($container);
        $this->registerChatServices($container);
        $this->registerModelServices($container);
        $this->registerPlatformFactory($container);
        $this->registerCommands($container);
    }

    private function registerAuthServices(ContainerBuilder $container): void
    {
        $container->setDefinition('copilot.auth_service', new Definition(AuthService::class))
            ->setArguments([
                new Reference('cache.app'),
                '%copilot.auth_token_expiration%'
            ])
            ->setPublic(false);

        $container->setDefinition('copilot.copilot_auth_service', new Definition(CopilotAuthService::class))
            ->setArguments([
                new Reference('http_client'),
                new Reference('copilot.configuration_manager'),
                new Reference('logger'),
                '%copilot.client_id%',
                '%copilot.device_code_url%',
                '%copilot.access_token_url%',
                '%copilot.api_key_url%'
            ])
            ->setPublic(false);
    }

    private function registerChatServices(ContainerBuilder $container): void
    {
        $container->setDefinition('copilot.model_client', new Definition(ModelClient::class))
            ->setArguments([new Reference('http_client')])
            ->setPublic(false);

        $container->setDefinition('copilot.result_converter', new Definition(ResultConverter::class))
            ->setPublic(false);
    }

    private function registerModelServices(ContainerBuilder $container): void
    {
        $container->setDefinition('copilot.model_capabilities_resolver', new Definition(ModelCapabilitiesResolver::class))
            ->setArguments(['%copilot.models%'])
            ->setPublic(false);

        $container->setAlias(ModelCapabilitiesResolverInterface::class, 'copilot.model_capabilities_resolver')
            ->setPublic(false);
    }

    private function registerPlatformFactory(ContainerBuilder $container): void
    {
        $container->setDefinition('copilot.platform_factory', new Definition(PlatformFactory::class))
            ->setPublic(false);
    }

    private function registerCommands(ContainerBuilder $container): void
    {
        // Register copilot commands
        $container->setDefinition('copilot.command.auth', new Definition(AuthCommand::class))
            ->setArguments([new Reference('copilot.copilot_auth_service')])
            ->addTag('console.command')
            ->setPublic(false);

        $container->setDefinition('copilot.command.logout', new Definition(LogoutCommand::class))
            ->setArguments([new Reference('copilot.copilot_auth_service')])
            ->addTag('console.command')
            ->setPublic(false);

        $container->setDefinition('copilot.command.status', new Definition(StatusCommand::class))
            ->setArguments([new Reference('copilot.copilot_auth_service')])
            ->addTag('console.command')
            ->setPublic(false);
    }
}