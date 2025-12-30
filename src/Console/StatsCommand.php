<?php

declare(strict_types=1);

namespace Authza\Console;

use Authza\Console\Helpers\OutputHelper;
use Authza\Console\Helpers\TableFormatter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class StatsCommand extends Command
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
            ->setName('stats')
            ->setDescription('Show statistics')
            ->addOption(
                'format',
                'f',
                InputOption::VALUE_OPTIONAL,
                'Output format (table|json)',
                'table'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = new OutputHelper($output);
        $format = $input->getOption('format');

        try {
            $graph = $this->container->getPermissionGraph();
            $stats = $graph->getStats();

            switch ($format) {
                case 'json':
                    $output->writeln(json_encode($stats, JSON_PRETTY_PRINT));
                    break;

                case 'table':
                default:
                    $output->writeln('');
                    $output->writeln('<fg=cyan>Permission Graph Statistics</>');
                    $output->writeln('');
                    $output->writeln("Total rules: {$stats['total_rules']}");
                    $output->writeln('');

                    if (!empty($stats['by_resource_type'])) {
                        $output->writeln('<fg=yellow>Rules by resource type:</>');
                        $tableData = [];
                        foreach ($stats['by_resource_type'] as $type => $count) {
                            $tableData[] = ['type' => $type, 'count' => $count];
                        }
                        $tableFormatter = new TableFormatter();
                        $tableFormatter->format($output, $tableData, ['Type', 'Count']);
                        $output->writeln('');
                    }

                    if (!empty($stats['by_subject_type'])) {
                        $output->writeln('<fg=yellow>Rules by subject type:</>');
                        $tableData = [];
                        foreach ($stats['by_subject_type'] as $type => $count) {
                            $tableData[] = ['type' => $type, 'count' => $count];
                        }
                        $tableFormatter = new TableFormatter();
                        $tableFormatter->format($output, $tableData, ['Type', 'Count']);
                    }
                    break;
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $helper->error('Stats failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
