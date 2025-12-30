<?php

declare(strict_types=1);

namespace Authza\Console;

use Authza\Console\Helpers\OutputHelper;
use Authza\Console\Helpers\ProgressHelper;
use Authza\Exceptions\DslParseException;
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
            // Detect parser
            $parser = $this->detectParser($file, $format);
            
            // Read file content
            if (!file_exists($file)) {
                throw new DslParseException("File not found: {$file}");
            }
            
            $content = file_get_contents($file);
            if ($content === false) {
                throw new DslParseException("Failed to read file: {$file}");
            }

            // Parse rules
            $rules = $parser->parse($content);

            if ($verbose) {
                $helper->info("Parsed {$file}");
                $helper->info("Found " . count($rules) . " rules");
            }

            // Validate rules
            $validator = $this->container->getDslValidator();
            $validation = $validator->validate($parser, $content);

            if (!$validation->isValid()) {
                $helper->error('Validation failed:');
                foreach ($validation->getErrors() as $error) {
                    $output->writeln("  <fg=red>{$error}</>");
                }
                return Command::INVALID;
            }

            if ($validation->hasWarnings()) {
                foreach ($validation->getWarnings() as $warning) {
                    $helper->warning($warning);
                }
            }

            if ($dryRun) {
                $helper->success('Dry-run: Validation passed');
                $helper->info(sprintf(
                    '%d rules would be imported, %d errors, %d warnings',
                    count($rules),
                    count($validation->getErrors()),
                    count($validation->getWarnings())
                ));
                return Command::SUCCESS;
            }

            // Import rules
            $importer = $this->container->getDslImporter();
            $imported = $importer->import($parser, $content);

            if ($verbose) {
                $helper->success("Imported {$imported} rules");
            }

            $helper->success(sprintf(
                '%d rules imported, %d errors, %d warnings',
                $imported,
                count($validation->getErrors()),
                count($validation->getWarnings())
            ));

            return Command::SUCCESS;
        } catch (DslParseException $e) {
            $helper->error('Import failed: ' . $e->getMessage());
            return 2;
        } catch (\Exception $e) {
            $helper->error('Import failed: ' . $e->getMessage());
            return 2;
        }
    }

    private function detectParser(string $file, ?string $format)
    {
        if ($format === 'json') {
            return $this->container->getJsonParser();
        }
        
        if ($format === 'line') {
            return $this->container->getLineParser();
        }

        // Auto-detect from extension
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if ($ext === 'json') {
            return $this->container->getJsonParser();
        }

        // Default to line parser for .dsl, .txt, or unknown
        return $this->container->getLineParser();
    }
}
