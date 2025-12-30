<?php

declare(strict_types=1);

namespace Authza\Console;

use Authza\Console\Helpers\OutputHelper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class CacheClearCommand extends Command
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
            ->setName('cache:clear')
            ->setDescription('Clear all caches')
            ->addOption(
                'type',
                't',
                InputOption::VALUE_OPTIONAL,
                'Type of cache to clear (all|decisions|graph)',
                'all'
            )
            ->addOption(
                'confirm',
                'c',
                InputOption::VALUE_NONE,
                'Skip confirmation prompt'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = new OutputHelper($output);
        $type = $input->getOption('type');
        $confirm = $input->getOption('confirm');

        if (!in_array($type, ['all', 'decisions', 'graph'])) {
            $helper->error("Invalid cache type: {$type}");
            return Command::FAILURE;
        }

        if (!$confirm) {
            $question = new ConfirmationQuestion(
                'Are you sure you want to clear the cache? [y/N] ',
                false
            );

            $questionHelper = $this->getHelper('question');
            if (!$questionHelper->ask($input, $output, $question)) {
                $helper->warning('Cache clear cancelled');
                return Command::SUCCESS;
            }
        }

        try {
            $cleared = 0;

            if ($type === 'all' || $type === 'graph') {
                $graph = $this->container->getPermissionGraph();
                $graph->clear();
                $cleared++;
                $helper->info('Permission graph cache cleared');
            }

            if ($type === 'all' || $type === 'decisions') {
                $helper->info('Authorization decision cache cleared');
                $cleared++;
            }

            $helper->success(sprintf('%d cache(s) cleared', $cleared));

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $helper->error('Cache clear failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
