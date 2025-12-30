<?php

declare(strict_types=1);

namespace Authza\Console\Helpers;

use Symfony\Component\Console\Output\OutputInterface;

class OutputHelper
{
    private OutputInterface $output;

    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
    }

    public function success(string $message): void
    {
        $this->output->writeln("<fg=green>✓ {$message}</>");
    }

    public function error(string $message): void
    {
        $this->output->writeln("<fg=red>✗ {$message}</>");
    }

    public function warning(string $message): void
    {
        $this->output->writeln("<fg=yellow>⚠ {$message}</>");
    }

    public function info(string $message): void
    {
        $this->output->writeln("<fg=blue>ℹ {$message}</>");
    }
}
