<?php

declare(strict_types=1);

namespace Authza\Console\Helpers;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\OutputInterface;

class TableFormatter
{
    /**
     * @param array<array<string, mixed>> $data
     * @param array<string> $headers
     */
    public function format(OutputInterface $output, array $data, array $headers): void
    {
        $table = new Table($output);
        $table->setHeaders($headers);

        foreach ($data as $row) {
            $tableRow = [];
            foreach ($headers as $header) {
                $key = strtolower($header);
                $tableRow[] = $row[$key] ?? '';
            }
            $table->addRow($tableRow);
        }

        $table->render();
    }
}
