<?php

declare(strict_types=1);

namespace Authza\Console;

use Authza\Console\Helpers\OutputHelper;
use Authza\Console\Helpers\ProgressHelper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ImportCommand extends Command
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
            ->setName('import')
            ->setDescription('Import DSL rules into permission graph')
            ->addOption(
                'file',
                'f',
                InputOption::VALUE_REQUIRED,
                'Path to DSL file'
            )
            ->addOption(
                'format',
                null,
                InputOption::VALUE_OPTIONAL,
                'Force specific format (json|line), auto-detect if not provided'
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Validate without importing'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = new OutputHelper($output);
        $file = $input->getOption('file');

        if (!$file) {
            $helper->error('The --file option is required');
            return Command::FAILURE;
        }

        $format = $input->getOption('format');
        $dryRun = $input->getOption('dry-run');
        $verbose = $output->isVerbose();

        try {
            $parser = $this->container->getDSLParser();
            $rules = $parser->parseFile($file, $format);

            if ($verbose) {
                $helper->info("Parsed {$file}");
                $helper->info("Found " . count($rules) . " rules");
            }

            $validation = $parser->validate($rules);

            if (!$validation['valid']) {
                $helper->error('Validation failed:');
                foreach ($validation['errors'] as $error) {
                    $output->writeln("  <fg=red>{$error}</>");
                }
                return Command::INVALID;
            }

            if (!empty($validation['warnings'])) {
                foreach ($validation['warnings'] as $warning) {
                    $helper->warning($warning);
                }
            }

            if ($dryRun) {
                $helper->success('Dry-run: Validation passed');
                $helper->info(sprintf(
                    '%d rules would be imported, %d errors, %d warnings',
                    count($rules),
                    count($validation['errors']),
                    count($validation['warnings'])
                ));
                return Command::SUCCESS;
            }

            $graph = $this->container->getPermissionGraph();
            $progress = new ProgressHelper($output);

            if ($verbose) {
                $progress->start(count($rules), 'Importing rules...');
            }

            $imported = 0;
            foreach ($rules as $rule) {
                $graph->addRule($rule);
                $imported++;
                if ($verbose) {
                    $progress->advance();
                }
            }

            if ($verbose) {
                $progress->finish();
            }

            $helper->success(sprintf(
                '%d rules imported, %d errors, %d warnings',
                $imported,
                count($validation['errors']),
                count($validation['warnings'])
            ));

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $helper->error('Import failed: ' . $e->getMessage());
            return 2;
        }
    }
}
