<?php

declare(strict_types=1);

namespace Authza\Console;

use Authza\Console\Helpers\OutputHelper;
use Authza\Console\Helpers\TableFormatter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ListCommand extends Command
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
            ->setName('list')
            ->setAliases(['policy:list'])
            ->setDescription('List all policies')
            ->addOption(
                'filter',
                'f',
                InputOption::VALUE_OPTIONAL,
                'Filter by subject, resource, or action'
            )
            ->addOption(
                'format',
                null,
                InputOption::VALUE_OPTIONAL,
                'Output format (table|json|yaml)',
                'table'
            )
            ->addOption(
                'resource',
                'r',
                InputOption::VALUE_OPTIONAL,
                'Filter by resource type'
            )
            ->addOption(
                'subject',
                's',
                InputOption::VALUE_OPTIONAL,
                'Filter by subject'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = new OutputHelper($output);
        $filter = $input->getOption('filter');
        $format = $input->getOption('format');
        $resource = $input->getOption('resource');
        $subject = $input->getOption('subject');

        try {
            $graph = $this->container->getPermissionGraph();
            $rules = $graph->getRules();

            $filteredRules = $this->applyFilters($rules, $filter, $resource, $subject);

            switch ($format) {
                case 'json':
                    $output->writeln(json_encode($filteredRules, JSON_PRETTY_PRINT));
                    break;

                case 'yaml':
                    $helper->warning('YAML format not yet implemented, using JSON');
                    $output->writeln(json_encode($filteredRules, JSON_PRETTY_PRINT));
                    break;

                case 'table':
                default:
                    if (empty($filteredRules)) {
                        $helper->warning('No policies found');
                    } else {
                        $tableFormatter = new TableFormatter();
                        $tableFormatter->format(
                            $output,
                            $filteredRules,
                            ['Subject', 'Resource', 'Action', 'Condition']
                        );
                    }
                    break;
            }

            $helper->info(sprintf(
                '%d policies total, %d filtered',
                count($rules),
                count($filteredRules)
            ));

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $helper->error('List failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * @param array<array<string, mixed>> $rules
     * @return array<array<string, mixed>>
     */
    private function applyFilters(
        array $rules,
        ?string $filter,
        ?string $resource,
        ?string $subject
    ): array {
        return array_filter($rules, function ($rule) use ($filter, $resource, $subject) {
            if ($filter) {
                $match = str_contains($rule['subject'] ?? '', $filter) ||
                         str_contains($rule['resource'] ?? '', $filter) ||
                         str_contains($rule['action'] ?? '', $filter);
                if (!$match) {
                    return false;
                }
            }

            if ($resource && !str_contains($rule['resource'] ?? '', $resource)) {
                return false;
            }

            if ($subject && ($rule['subject'] ?? '') !== $subject) {
                return false;
            }

            return true;
        });
    }
}
