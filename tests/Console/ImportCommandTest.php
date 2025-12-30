<?php

declare(strict_types=1);

namespace Authza\Tests\Console;

use Authza\Console\ImportCommand;
use Authza\Console\ServiceContainer;
use Authza\Console\ConsoleConfig;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class ImportCommandTest extends TestCase
{
    private string $testFile;
    private string $storageFile;

    protected function setUp(): void
    {
        $this->testFile = sys_get_temp_dir() . '/test_rules_' . uniqid() . '.json';
        $this->storageFile = sys_get_temp_dir() . '/test_graph_' . uniqid() . '.json';

        $rules = [
            ['subject' => 'role:admin', 'resource' => 'invoice', 'action' => 'create'],
            ['subject' => 'role:admin', 'resource' => 'invoice', 'action' => 'edit']
        ];

        file_put_contents($this->testFile, json_encode($rules));
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testFile)) {
            unlink($this->testFile);
        }
        if (file_exists($this->storageFile)) {
            unlink($this->storageFile);
        }
    }

    public function testSuccessfulImport(): void
    {
        $config = new ConsoleConfig(['graph' => ['storage' => $this->storageFile]]);
        $container = new ServiceContainer($config);
        $command = new ImportCommand($container);

        $application = new Application();
        $application->add($command);

        $commandTester = new CommandTester($command);
        $commandTester->execute([
            '--file' => $this->testFile
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('2 rules imported', $output);
        $this->assertStringContainsString('0 errors', $output);
        $this->assertEquals(0, $commandTester->getStatusCode());
    }

    public function testDryRunMode(): void
    {
        $config = new ConsoleConfig(['graph' => ['storage' => $this->storageFile]]);
        $container = new ServiceContainer($config);
        $command = new ImportCommand($container);

        $commandTester = new CommandTester($command);
        $commandTester->execute([
            '--file' => $this->testFile,
            '--dry-run' => true
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Dry-run', $output);
        $this->assertStringContainsString('Validation passed', $output);
        $this->assertEquals(0, $commandTester->getStatusCode());
        $this->assertFileDoesNotExist($this->storageFile);
    }

    public function testFileNotFound(): void
    {
        $config = new ConsoleConfig(['graph' => ['storage' => $this->storageFile]]);
        $container = new ServiceContainer($config);
        $command = new ImportCommand($container);

        $commandTester = new CommandTester($command);
        $commandTester->execute([
            '--file' => '/nonexistent/file.json'
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('failed', $output);
        $this->assertNotEquals(0, $commandTester->getStatusCode());
    }

    public function testInvalidDSL(): void
    {
        $invalidFile = sys_get_temp_dir() . '/invalid_' . uniqid() . '.json';
        file_put_contents($invalidFile, '{"invalid": "json"');

        $config = new ConsoleConfig(['graph' => ['storage' => $this->storageFile]]);
        $container = new ServiceContainer($config);
        $command = new ImportCommand($container);

        $commandTester = new CommandTester($command);
        $commandTester->execute([
            '--file' => $invalidFile
        ]);

        $this->assertNotEquals(0, $commandTester->getStatusCode());

        unlink($invalidFile);
    }

    public function testVerboseOutput(): void
    {
        $config = new ConsoleConfig(['graph' => ['storage' => $this->storageFile]]);
        $container = new ServiceContainer($config);
        $command = new ImportCommand($container);

        $commandTester = new CommandTester($command);
        $commandTester->execute([
            '--file' => $this->testFile
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('2 rules imported', $output);
        $this->assertEquals(0, $commandTester->getStatusCode());
    }
}
