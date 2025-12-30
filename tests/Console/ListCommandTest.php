<?php

declare(strict_types=1);

namespace Authza\Tests\Console;

use Authza\Console\ListCommand;
use Authza\Console\ServiceContainer;
use Authza\Console\ConsoleConfig;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class ListCommandTest extends TestCase
{
    private string $storageFile;

    protected function setUp(): void
    {
        $this->storageFile = sys_get_temp_dir() . '/test_graph_' . uniqid() . '.json';

        $data = [
            'rules' => [
                'key1' => ['subject' => 'role:admin', 'resource' => 'invoice', 'action' => 'create'],
                'key2' => ['subject' => 'role:admin', 'resource' => 'invoice', 'action' => 'edit'],
                'key3' => ['subject' => 'role:accountant', 'resource' => 'invoice', 'action' => 'view']
            ],
            'stats' => ['total_rules' => 3, 'by_resource_type' => ['invoice' => 3], 'by_subject_type' => ['role' => 3]]
        ];

        file_put_contents($this->storageFile, json_encode($data));
    }

    protected function tearDown(): void
    {
        if (file_exists($this->storageFile)) {
            unlink($this->storageFile);
        }
    }

    public function testListAllPolicies(): void
    {
        $config = new ConsoleConfig(['graph' => ['storage' => $this->storageFile]]);
        $container = new ServiceContainer($config);
        $command = new ListCommand($container);

        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('3 policies total', $output);
        $this->assertEquals(0, $commandTester->getStatusCode());
    }

    public function testListWithFilter(): void
    {
        $config = new ConsoleConfig(['graph' => ['storage' => $this->storageFile]]);
        $container = new ServiceContainer($config);
        $command = new ListCommand($container);

        $commandTester = new CommandTester($command);
        $commandTester->execute([
            '--filter' => 'admin'
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('3 policies total', $output);
        $this->assertStringContainsString('2 filtered', $output);
        $this->assertEquals(0, $commandTester->getStatusCode());
    }

    public function testListJsonFormat(): void
    {
        $config = new ConsoleConfig(['graph' => ['storage' => $this->storageFile]]);
        $container = new ServiceContainer($config);
        $command = new ListCommand($container);

        $commandTester = new CommandTester($command);
        $commandTester->execute([
            '--format' => 'json'
        ]);

        $output = $commandTester->getDisplay();

        // Extract just the JSON part (before the info message)
        $lines = explode("\n", $output);
        $jsonLines = [];
        $inJson = false;

        foreach ($lines as $line) {
            if (str_starts_with($line, '[')) {
                $inJson = true;
            }
            if ($inJson) {
                $jsonLines[] = $line;
                if (str_starts_with($line, ']')) {
                    break;
                }
            }
        }

        $jsonOutput = implode("\n", $jsonLines);
        $this->assertJson($jsonOutput);
        $data = json_decode($jsonOutput, true);
        $this->assertIsArray($data);
    }

    public function testListTableFormat(): void
    {
        $config = new ConsoleConfig(['graph' => ['storage' => $this->storageFile]]);
        $container = new ServiceContainer($config);
        $command = new ListCommand($container);

        $commandTester = new CommandTester($command);
        $commandTester->execute([
            '--format' => 'table'
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Subject', $output);
        $this->assertStringContainsString('Resource', $output);
        $this->assertStringContainsString('Action', $output);
    }
}
