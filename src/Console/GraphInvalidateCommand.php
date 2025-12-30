<?php

declare(strict_types=1);

namespace Authza\Console;

use Authza\Console\Helpers\OutputHelper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GraphInvalidateCommand extends Command
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
            ->setName('graph:invalidate')
            ->setDescription('Invalidate permission graph cache')
            ->addOption(
                'subject',
                's',
                InputOption::VALUE_OPTIONAL,
                'Invalidate specific subject only'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = new OutputHelper($output);
        $subject = $input->getOption('subject');

        try {
            $graph = $this->container->getPermissionGraph();
            $graph->clear($subject);

            if ($subject) {
                $helper->success("Cache invalidated for subject: {$subject}");
            } else {
                $helper->success('Permission graph cache invalidated');
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $helper->error('Cache invalidation failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
