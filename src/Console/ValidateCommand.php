<?php

declare(strict_types=1);

namespace Authza\Console;

use Authza\Console\Helpers\OutputHelper;
use Authza\Exceptions\DslParseException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ValidateCommand extends Command
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
            ->setName('validate')
            ->setDescription('Validate DSL rules')
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
                'Force specific format (json|line)'
            )
            ->addOption(
                'strict',
                null,
                InputOption::VALUE_NONE,
                'Treat warnings as errors'
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
        $strict = $input->getOption('strict');

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

            // Validate
            $validator = $this->container->getDslValidator();
            $validation = $validator->validate($parser, $content);

            if (!$validation->isValid()) {
                $helper->error('Validation errors:');
                foreach ($validation->getErrors() as $error) {
                    $output->writeln("  <fg=red>{$error}</>");
                }
            }

            if ($validation->hasWarnings()) {
                $label = $strict ? 'Validation errors' : 'Validation warnings';
                $helper->warning($label . ':');
                foreach ($validation->getWarnings() as $warning) {
                    $output->writeln("  <fg=yellow>{$warning}</>");
                }
            }

            $isValid = $validation->isValid() && (!$strict || !$validation->hasWarnings());

            if ($isValid) {
                $helper->success('Valid');
            } else {
                $helper->error('Invalid');
            }

            $output->writeln(sprintf(
                'Summary: %d error(s), %d warning(s)',
                count($validation->getErrors()),
                count($validation->getWarnings())
            ));

            return $isValid ? Command::SUCCESS : Command::FAILURE;
        } catch (DslParseException $e) {
            $helper->error('Validation failed: ' . $e->getMessage());
            return Command::FAILURE;
        } catch (\Exception $e) {
            $helper->error('Validation failed: ' . $e->getMessage());
            return Command::FAILURE;
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

        // Default to line parser
        return $this->container->getLineParser();
    }
}
