<?php

declare(strict_types=1);

namespace Authza\Console\Helpers;

use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;

class ProgressHelper
{
    private OutputInterface $output;
    private ?ProgressBar $progressBar = null;

    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
    }

    public function start(int $max, string $message = ''): void
    {
        if ($message) {
            $this->output->writeln($message);
        }

        $this->progressBar = new ProgressBar($this->output, $max);
        $this->progressBar->start();
    }

    public function advance(int $step = 1): void
    {
        if ($this->progressBar) {
            $this->progressBar->advance($step);
        }
    }

    public function finish(): void
    {
        if ($this->progressBar) {
            $this->progressBar->finish();
            $this->output->writeln('');
            $this->progressBar = null;
        }
    }
}
