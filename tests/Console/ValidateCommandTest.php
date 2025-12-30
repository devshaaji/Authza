<?php

declare(strict_types=1);

namespace Authza\Tests\Console;

use Authza\Console\ValidateCommand;
use Authza\Console\ServiceContainer;
use Authza\Console\ConsoleConfig;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class ValidateCommandTest extends TestCase
{
    private string $validFile;
    private string $invalidFile;

    protected function setUp(): void
    {
        $this->validFile = sys_get_temp_dir() . '/valid_' . uniqid() . '.json';
        $this->invalidFile = sys_get_temp_dir() . '/invalid_' . uniqid() . '.json';

        $validRules = [
            ['subject' => 'role:admin', 'resource' => 'invoice', 'action' => 'create'],
            ['subject' => 'role:admin', 'resource' => 'invoice', 'action' => 'edit']
        ];
        file_put_contents($this->validFile, json_encode($validRules));

        $invalidRules = [
            ['subject' => 'role:admin', 'action' => 'create'],
            ['resource' => 'invoice', 'action' => 'edit']
        ];
        file_put_contents($this->invalidFile, json_encode($invalidRules));
    }

    protected function tearDown(): void
    {
        if (file_exists($this->validFile)) {
            unlink($this->validFile);
        }
        if (file_exists($this->invalidFile)) {
            unlink($this->invalidFile);
        }
    }

    public function testValidationOfValidDSL(): void
    {
        $config = new ConsoleConfig();
        $container = new ServiceContainer($config);
        $command = new ValidateCommand($container);

        $commandTester = new CommandTester($command);
        $commandTester->execute([
            '--file' => $this->validFile
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Valid', $output);
        $this->assertEquals(0, $commandTester->getStatusCode());
    }

    public function testValidationOfInvalidDSL(): void
    {
        $config = new ConsoleConfig();
        $container = new ServiceContainer($config);
        $command = new ValidateCommand($container);

        $commandTester = new CommandTester($command);
        $commandTester->execute([
            '--file' => $this->invalidFile
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Invalid', $output);
        $this->assertNotEquals(0, $commandTester->getStatusCode());
    }

    public function testStrictMode(): void
    {
        $config = new ConsoleConfig();
        $container = new ServiceContainer($config);
        $command = new ValidateCommand($container);

        $commandTester = new CommandTester($command);
        $commandTester->execute([
            '--file' => $this->validFile,
            '--strict' => true
        ]);

        $this->assertEquals(0, $commandTester->getStatusCode());
    }
}
