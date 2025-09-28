<?php

declare(strict_types=1);

namespace Akawaka\Bridge\Copilot\Command;

use Akawaka\Bridge\Copilot\Exception\CopilotException;
use Akawaka\Bridge\Copilot\Auth\CopilotAuthService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'copilot:auth',
    description: 'Authenticate with GitHub Copilot and store the access token in configuration'
)]
final class AuthCommand extends Command
{
    public function __construct(
        private readonly CopilotAuthService $copilotAuthService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'timeout',
                't',
                InputOption::VALUE_OPTIONAL,
                'Authentication timeout in seconds',
                300
            )
            ->addOption(
                'check-existing',
                'c',
                InputOption::VALUE_NONE,
                'Check if there is already a valid token before starting authentication'
            )
            ->setHelp(
                <<<'HELP'
                This command will authenticate your application with GitHub Copilot using the device flow.

                Usage:
                  <info>php bin/console copilot:auth</info>

                The command will:
                1. Generate a device code and user code
                2. Display instructions for user authentication
                3. Poll GitHub for authorization completion
                4. Store the access token in configuration upon successful authentication

                Options:
                  <info>--timeout, -t</info>     Set authentication timeout in seconds (default: 300)
                  <info>--check-existing, -c</info> Check for existing valid token before starting

                Examples:
                  <info>php bin/console copilot:auth --timeout=600</info>
                  <info>php bin/console copilot:auth --check-existing</info>
                HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $timeout = (int) $input->getOption('timeout');
        $checkExisting = $input->getOption('check-existing');

        $io->title('GitHub Copilot Authentication');

        // Check for existing valid token if requested
        if ($checkExisting) {
            $io->section('Checking existing authentication');

            try {
                $existingToken = $this->copilotAuthService->getAccessToken();
                if ($existingToken) {
                    $io->success('Valid GitHub Copilot token already exists in configuration.');
                    $io->writeln(sprintf('Token: %s...', substr($existingToken, 0, 10)));
                    return Command::SUCCESS;
                }
                $io->info('No valid token found. Starting authentication process...');
            } catch (CopilotException $e) {
                $io->warning(sprintf('Error checking existing token: %s', $e->getMessage()));
                $io->info('Starting fresh authentication process...');
            }
        }

        // Step 1: Get device code
        $io->section('Step 1: Getting device code');

        try {
            $deviceInfo = $this->copilotAuthService->authorize();
        } catch (CopilotException $e) {
            $io->error(sprintf('Failed to get device code: %s', $e->getMessage()));
            return Command::FAILURE;
        }

        $io->success('Device code generated successfully!');

        // Step 2: Display user instructions
        $io->section('Step 2: User Authentication Required');

        $io->writeln([
            '<fg=yellow>Please complete the following steps to authenticate:</fg=yellow>',
            '',
            sprintf('1. Open your web browser and go to: <href=%s>%s</>', $deviceInfo['verification'], $deviceInfo['verification']),
            sprintf('2. Enter the user code: <fg=cyan;options=bold>%s</>', $deviceInfo['user']),
            '3. Follow the instructions to authorize the application',
            '',
            '<fg=green>Once you have completed the authorization, this command will automatically continue...</fg=green>',
            ''
        ]);

        // Step 3: Poll for authorization
        $io->section('Step 3: Waiting for authorization');

        $progressBar = new ProgressBar($output, $timeout / $deviceInfo['interval']);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %message%');
        $progressBar->setMessage('Waiting for user authorization...');
        $progressBar->start();

        $startTime = time();
        $pollInterval = $deviceInfo['interval'];

        while ((time() - $startTime) < $timeout) {
            try {
                $result = $this->copilotAuthService->poll($deviceInfo['device']);

                switch ($result) {
                    case 'complete':
                        $progressBar->setMessage('Authentication completed!');
                        $progressBar->finish();
                        $io->writeln('');

                        // Step 4: Verify token and get access token
                        $io->section('Step 4: Retrieving access token');

                        try {
                            $accessToken = $this->copilotAuthService->getAccessToken();
                            if ($accessToken) {
                                $io->success([
                                    'GitHub Copilot authentication completed successfully!',
                                    'Access token has been stored in cache.',
                                    sprintf('Token preview: %s...', substr($accessToken, 0, 15))
                                ]);
                                return Command::SUCCESS;
                            } else {
                                $io->error('Failed to retrieve access token after authentication.');
                                return Command::FAILURE;
                            }
                        } catch (CopilotException $e) {
                            $io->error(sprintf('Failed to retrieve access token: %s', $e->getMessage()));
                            return Command::FAILURE;
                        }

                    case 'pending':
                        $progressBar->setMessage('Still waiting for authorization...');
                        break;

                    case 'failed':
                        $progressBar->setMessage('Authentication failed!');
                        $progressBar->finish();
                        $io->writeln('');
                        $io->error('Authentication failed. Please try again.');
                        return Command::FAILURE;

                    default:
                        $progressBar->setMessage('Unknown status received...');
                        break;
                }
            } catch (CopilotException $e) {
                $progressBar->setMessage(sprintf('Error: %s', $e->getMessage()));
                $progressBar->finish();
                $io->writeln('');
                $io->error(sprintf('Authentication error: %s', $e->getMessage()));
                return Command::FAILURE;
            }

            $progressBar->advance();
            sleep($pollInterval);
        }

        // Timeout reached
        $progressBar->setMessage('Authentication timed out!');
        $progressBar->finish();
        $io->writeln('');
        $io->error(sprintf('Authentication timed out after %d seconds. Please try again.', $timeout));

        return Command::FAILURE;
    }
}