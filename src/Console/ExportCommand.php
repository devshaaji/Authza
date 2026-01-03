<?php

declare(strict_types=1);

namespace Authza\Console;

use Authza\Console\Helpers\OutputHelper;
use Authza\DSL\DslExporter;
use Authza\DSL\PolicyDefinition;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ExportCommand extends Command
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
            ->setName('export')
            ->setDescription('Export policies to DSL format')
            ->addOption(
                'output',
                'o',
                InputOption::VALUE_REQUIRED,
                'Output file path'
            )
            ->addOption(
                'format',
                null,
                InputOption::VALUE_OPTIONAL,
                'Export format (json|line)',
                'json'
            )
            ->addOption(
                'filter',
                null,
                InputOption::VALUE_OPTIONAL,
                'Filter rules by pattern (e.g., "role:admin", "invoice")'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = new OutputHelper($output);
        $outputFile = $input->getOption('output');

        if (!$outputFile) {
            $helper->error('The --output option is required');
            return Command::FAILURE;
        }

        $format = $input->getOption('format');
        $filter = $input->getOption('filter');
        $verbose = $output->isVerbose();

        try {
            $graph = $this->container->getPermissionGraph();
            $rules = $graph->getRules();

            if ($filter) {
                $rules = $this->filterRules($rules, $filter);
                if ($verbose) {
                    $helper->info("Applied filter: {$filter}");
                }
            }

            // Convert rules to PolicyDefinition objects for export
            $policies = array_map(
                fn($rule) => new PolicyDefinition(
                    $rule['subject'],
                    $rule['resource'],
                    $rule['action'],
                    $rule['condition'] ?? null,
                    $rule['effect'] ?? 'allow'
                ),
                $rules
            );

            $exporter = new DslExporter($policies);
            $content = $exporter->export($format);

            if (file_put_contents($outputFile, $content) === false) {
                $helper->error("Failed to write to file: {$outputFile}");
                return Command::FAILURE;
            }

            if ($verbose) {
                $helper->info("Exported to {$outputFile}");
            }

            $helper->success(sprintf('%d rules exported', count($rules)));

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $helper->error('Export failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * @param array<array<string, mixed>> $rules
     * @return array<array<string, mixed>>
     */
    private function filterRules(array $rules, string $filter): array
    {
        return array_filter($rules, function ($rule) use ($filter) {
            return
                str_contains($rule['subject'] ?? '', $filter) ||
                str_contains($rule['resource'] ?? '', $filter) ||
                str_contains($rule['action'] ?? '', $filter);
        });
    }
}
