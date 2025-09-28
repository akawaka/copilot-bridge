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
    name: 'copilot:status',
    description: 'Check GitHub Copilot authentication status'
)]
final class StatusCommand extends Command
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
            This command checks the current GitHub Copilot authentication status.

            Usage:
              <info>php bin/console copilot:status</info>

            The command will:
            1. Check if there is an authentication token stored
            2. Validate if the token is still active
            3. Display token information if available

            This is useful for:
            - Verifying if you're authenticated
            - Checking token expiration
            - Troubleshooting authentication issues

            Examples:
              <info>php bin/console copilot:status</info>
            HELP
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('GitHub Copilot Authentication Status');

        try {
            $accessToken = $this->copilotAuthService->getAccessToken();

            if ($accessToken && $accessToken !== '') {
                $io->success([
                    'GitHub Copilot authentication is active.',
                    sprintf('Token preview: %s...', substr($accessToken, 0, 15)),
                    'You can use GitHub Copilot features.'
                ]);

                // Display additional token info
                $io->section('Token Details');
                $io->horizontalTable(
                    ['Property', 'Value'],
                    [
                        ['Token Length', strlen($accessToken) . ' characters'],
                        ['Token Preview', substr($accessToken, 0, 20) . '...'],
                        ['Status', '<fg=green>Valid</>'],
                    ]
                );

                return Command::SUCCESS;
            } else {
                $io->warning([
                    'No GitHub Copilot authentication token found.',
                    'You need to authenticate first.'
                ]);

                $io->note('Run "copilot:auth" to authenticate with GitHub Copilot.');
                return Command::FAILURE;
            }

        } catch (CopilotException $e) {
            $io->error([
                'Failed to check GitHub Copilot authentication status.',
                sprintf('Error: %s', $e->getMessage())
            ]);

            $io->note([
                'This might indicate:',
                '- Authentication token has expired',
                '- Configuration file is corrupted',
                '- Network connectivity issues',
                '',
                'Try running "copilot:auth" to re-authenticate.'
            ]);

            return Command::FAILURE;
        }
    }
}