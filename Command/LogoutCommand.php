<?php

declare(strict_types=1);

namespace Akawaka\Bridge\Copilot\Command;

use Akawaka\Bridge\Copilot\Exception\CopilotException;
use Akawaka\Bridge\Copilot\Auth\CopilotAuthService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'copilot:logout',
    description: 'Remove GitHub Copilot authentication token from configuration'
)]
final class LogoutCommand extends Command
{
    public function __construct(
        private readonly CopilotAuthService $copilotAuthService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setHelp(
            <<<'HELP'
            This command will remove the stored GitHub Copilot authentication token from the configuration.

            Usage:
              <info>php bin/console copilot:logout</info>

            The command will:
            1. Check if there is an existing authentication token
            2. Remove the token from storage
            3. Confirm successful logout

            After running this command, you will need to run <info>copilot:auth</info> again
            to re-authenticate with GitHub Copilot.

            Examples:
              <info>php bin/console copilot:logout</info>
            HELP
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('GitHub Copilot Logout');

        // Check if there is an existing token
        try {
            $existingToken = $this->copilotAuthService->getAccessToken();

            if (!$existingToken) {
                $io->info('No GitHub Copilot authentication token found in configuration.');
                $io->note('You are already logged out. Use "copilot:auth" to authenticate.');
                return Command::SUCCESS;
            }

            $io->writeln(sprintf('Found existing token: %s...', substr($existingToken, 0, 15)));

        } catch (CopilotException $e) {
            $io->warning(sprintf('Could not check existing token: %s', $e->getMessage()));
            $io->info('Proceeding with logout to clear any stored configuration...');
        }

        // Remove tokens
        $io->section('Removing authentication tokens');

        try {
            $this->copilotAuthService->removeTokens();
            $io->success([
                'GitHub Copilot logout completed successfully!',
                'All authentication tokens have been removed from configuration.',
                'You will need to run "copilot:auth" to authenticate again.'
            ]);

            return Command::SUCCESS;

        } catch (CopilotException $e) {
            $io->error(sprintf('Failed to remove authentication tokens: %s', $e->getMessage()));
            return Command::FAILURE;
        }
    }
}