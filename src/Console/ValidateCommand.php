<?php

declare(strict_types=1);

namespace Authza\Console;

use Authza\Console\Helpers\OutputHelper;
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
            $parser = $this->container->getDSLParser();
            $rules = $parser->parseFile($file, $format);

            $validation = $parser->validate($rules);

            if (!empty($validation['errors'])) {
                $helper->error('Validation errors:');
                foreach ($validation['errors'] as $error) {
                    $output->writeln("  <fg=red>{$error}</>");
                }
            }

            if (!empty($validation['warnings'])) {
                $label = $strict ? 'Validation errors' : 'Validation warnings';
                $helper->warning($label . ':');
                foreach ($validation['warnings'] as $warning) {
                    $output->writeln("  <fg=yellow>{$warning}</>");
                }
            }

            $isValid = $validation['valid'] && (!$strict || empty($validation['warnings']));

            if ($isValid) {
                $helper->success('Valid');
            } else {
                $helper->error('Invalid');
            }

            $output->writeln(sprintf(
                'Summary: %d error(s), %d warning(s)',
                count($validation['errors']),
                count($validation['warnings'])
            ));

            return $isValid ? Command::SUCCESS : Command::FAILURE;
        } catch (\Exception $e) {
            $helper->error('Validation failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
