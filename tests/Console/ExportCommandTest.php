<?php

declare(strict_types=1);

namespace Authza\Tests\Console;

use Authza\Console\ExportCommand;
use Authza\Console\ServiceContainer;
use Authza\Console\ConsoleConfig;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class ExportCommandTest extends TestCase
{
    private string $storageFile;
    private string $outputFile;

    protected function setUp(): void
    {
        $this->storageFile = sys_get_temp_dir() . '/test_graph_' . uniqid() . '.json';
        $this->outputFile = sys_get_temp_dir() . '/test_export_' . uniqid() . '.json';

        $data = [
            'rules' => [
                'key1' => ['subject' => 'role:admin', 'resource' => 'invoice', 'action' => 'create'],
                'key2' => ['subject' => 'role:admin', 'resource' => 'invoice', 'action' => 'edit']
            ],
            'stats' => ['total_rules' => 2, 'by_resource_type' => ['invoice' => 2], 'by_subject_type' => ['role' => 2]]
        ];

        file_put_contents($this->storageFile, json_encode($data));
    }

    protected function tearDown(): void
    {
        if (file_exists($this->storageFile)) {
            unlink($this->storageFile);
        }
        if (file_exists($this->outputFile)) {
            unlink($this->outputFile);
        }
    }

    public function testExportToJson(): void
    {
        $config = new ConsoleConfig(['graph' => ['storage' => $this->storageFile]]);
        $container = new ServiceContainer($config);
        $command = new ExportCommand($container);

        $commandTester = new CommandTester($command);
        $commandTester->execute([
            '--output' => $this->outputFile,
            '--format' => 'json'
        ]);

        $this->assertEquals(0, $commandTester->getStatusCode());
        $this->assertFileExists($this->outputFile);

        $content = json_decode(file_get_contents($this->outputFile), true);
        $this->assertIsArray($content);
        $this->assertCount(2, $content);
    }

    public function testExportToLineFormat(): void
    {
        $config = new ConsoleConfig(['graph' => ['storage' => $this->storageFile]]);
        $container = new ServiceContainer($config);
        $command = new ExportCommand($container);

        $commandTester = new CommandTester($command);
        $commandTester->execute([
            '--output' => $this->outputFile,
            '--format' => 'line'
        ]);

        $this->assertEquals(0, $commandTester->getStatusCode());
        $this->assertFileExists($this->outputFile);

        $content = file_get_contents($this->outputFile);
        $this->assertStringContainsString('role:admin', $content);
        $this->assertStringContainsString('invoice', $content);
    }

    public function testExportWithFilter(): void
    {
        $config = new ConsoleConfig(['graph' => ['storage' => $this->storageFile]]);
        $container = new ServiceContainer($config);
        $command = new ExportCommand($container);

        $commandTester = new CommandTester($command);
        $commandTester->execute([
            '--output' => $this->outputFile,
            '--format' => 'json',
            '--filter' => 'admin'
        ]);

        $output = $commandTester->getDisplay();
        $this->assertEquals(0, $commandTester->getStatusCode());
        $this->assertStringContainsString('2 rules exported', $output);
    }
}
