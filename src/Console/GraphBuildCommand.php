<?php

declare(strict_types=1);

namespace Authza\Console;

use Authza\Console\Helpers\OutputHelper;
use Authza\Console\Helpers\ProgressHelper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GraphBuildCommand extends Command
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
            ->setName('graph:build')
            ->setDescription('Build/rebuild permission graph')
            ->addOption(
                'source',
                's',
                InputOption::VALUE_OPTIONAL,
                'DSL file to build from'
            )
            ->addOption(
                'clear',
                'c',
                InputOption::VALUE_NONE,
                'Clear existing graph before building'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = new OutputHelper($output);
        $source = $input->getOption('source');
        $clear = $input->getOption('clear');
        $verbose = $output->isVerbose();

        try {
            $graph = $this->container->getPermissionGraph();

            if ($clear) {
                $graph->clear();
                if ($verbose) {
                    $helper->info('Cleared existing graph');
                }
            }

            if ($source) {
                // Choose parser based on file extension
                $extension = strtolower(pathinfo($source, PATHINFO_EXTENSION));
                $parser = $extension === 'json' 
                    ? $this->container->getJsonParser() 
                    : $this->container->getLineParser();
                
                // Read file and parse content
                if (!file_exists($source)) {
                    throw new \RuntimeException("File not found: {$source}");
                }
                
                $content = file_get_contents($source);
                if ($content === false) {
                    throw new \RuntimeException("Failed to read file: {$source}");
                }
                
                $rules = $parser->parse($content);

                $progress = new ProgressHelper($output);
                if ($verbose) {
                    $progress->start(count($rules), 'Building graph...');
                }

                foreach ($rules as $rule) {
                    $graph->addRule($rule);
                    if ($verbose) {
                        $progress->advance();
                    }
                }

                if ($verbose) {
                    $progress->finish();
                }
            }

            $stats = $graph->getStats();

            // Save graph to storage
            $saved = $this->container->saveGraphToStorage();
            if ($verbose) {
                if ($saved) {
                    $helper->success("Graph saved to storage");
                } else {
                    $helper->warning("Could not save graph to storage (check graph.storage config)");
                }
            }

            $helper->success(sprintf(
                '%d permissions precomputed, %d resource types, %d subject types',
                $stats['total_rules'],
                count($stats['by_resource_type']),
                count($stats['by_subject_type'])
            ));

            if ($verbose) {
                $output->writeln('');
                $output->writeln('Resource types:');
                foreach ($stats['by_resource_type'] as $type => $count) {
                    $output->writeln("  {$type}: {$count}");
                }

                $output->writeln('');
                $output->writeln('Subject types:');
                foreach ($stats['by_subject_type'] as $type => $count) {
                    $output->writeln("  {$type}: {$count}");
                }
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $helper->error('Graph build failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
