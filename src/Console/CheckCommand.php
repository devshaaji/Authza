<?php

declare(strict_types=1);

namespace Authza\Console;

use Authza\Console\Helpers\OutputHelper;
use Authza\Interfaces\SubjectInterface;
use Authza\Interfaces\ResourceInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CheckCommand extends Command
{
    private ServiceContainer $container;

    public function __construct(ServiceContainer $container)
    {
        parent::__construct();
        $this->container = $container;
    }

    protected function configure(): void
    {
        $this
            ->setName('check')
            ->setDescription('Test permission check')
            ->addOption(
                'user',
                'u',
                InputOption::VALUE_REQUIRED,
                'User/subject ID'
            )
            ->addOption(
                'resource',
                'r',
                InputOption::VALUE_REQUIRED,
                'Resource in format "type:id" (e.g., "invoice:123")'
            )
            ->addOption(
                'action',
                'a',
                InputOption::VALUE_REQUIRED,
                'Action to check (e.g., "edit", "view")'
            )
            ->addOption(
                'roles',
                null,
                InputOption::VALUE_OPTIONAL,
                'Comma-separated roles for the user'
            )
            ->addOption(
                'context',
                null,
                InputOption::VALUE_OPTIONAL,
                'JSON context data'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = new OutputHelper($output);
        $userId = $input->getOption('user');
        $resourceStr = $input->getOption('resource');
        $action = $input->getOption('action');

        if (!$userId || !$resourceStr || !$action) {
            $helper->error('The --user, --resource, and --action options are required');
            return Command::FAILURE;
        }

        $roles = $input->getOption('roles');
        $contextJson = $input->getOption('context');
        $verbose = $output->isVerbose();

        try {
            $rolesArray = $roles ? explode(',', $roles) : [];
            $context = $contextJson ? json_decode($contextJson, true) : [];

            if ($contextJson && $context === null) {
                $helper->error('Invalid JSON context');
                return Command::FAILURE;
            }

            $parts = explode(':', $resourceStr, 2);
            $resourceType = $parts[0];
            $resourceId = $parts[1] ?? null;

            $subject = new class ($userId, $rolesArray) implements SubjectInterface {
                public function __construct(private string|int $id, private array $roles)
                {
                }

                public function getId(): string|int
                {
                    return $this->id;
                }

                public function getRoles(): array
                {
                    return $this->roles;
                }

                public function getAttributes(): array
                {
                    return [];
                }
            };

            $resource = new class ($resourceType, $resourceId) implements ResourceInterface {
                public function __construct(private string $type, private string|int|null $id)
                {
                }

                public function getType(): string
                {
                    return $this->type;
                }

                public function getId(): string|int|null
                {
                    return $this->id;
                }

                public function getAttributes(): array
                {
                    return [];
                }
            };

            $authz = $this->container->getAuthorization();
            $allowed = $authz->can($subject, $action, $resource, $context);

            if ($verbose) {
                $output->writeln('');
                $output->writeln('Evaluation details:');
                $output->writeln("  Subject: {$userId}");
                $output->writeln("  Roles: " . implode(', ', $rolesArray));
                $output->writeln("  Resource: {$resourceType}:{$resourceId}");
                $output->writeln("  Action: {$action}");
                $output->writeln('');
            }

            if ($allowed) {
                $helper->success('ALLOWED');
                return Command::SUCCESS;
            } else {
                $helper->error('DENIED');
                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $helper->error('Check failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
