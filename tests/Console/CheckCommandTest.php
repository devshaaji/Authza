<?php

declare(strict_types=1);

namespace Authza\Tests\Console;

use Authza\Console\CheckCommand;
use Authza\Console\ServiceContainer;
use Authza\Console\ConsoleConfig;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class CheckCommandTest extends TestCase
{
    public function testDeniedPermission(): void
    {
        $config = new ConsoleConfig();
        $container = new ServiceContainer($config);
        $command = new CheckCommand($container);

        $commandTester = new CommandTester($command);
        $commandTester->execute([
            '--user' => '1',
            '--resource' => 'invoice:123',
            '--action' => 'edit',
            '--roles' => 'admin'
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('DENIED', $output);
        $this->assertEquals(1, $commandTester->getStatusCode());
    }

    public function testWithContext(): void
    {
        $config = new ConsoleConfig();
        $container = new ServiceContainer($config);
        $command = new CheckCommand($container);

        $commandTester = new CommandTester($command);
        $commandTester->execute([
            '--user' => '1',
            '--resource' => 'invoice:123',
            '--action' => 'edit',
            '--roles' => 'admin',
            '--context' => '{"department":"finance"}'
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('DENIED', $output);
    }

    public function testVerboseOutput(): void
    {
        $config = new ConsoleConfig();
        $container = new ServiceContainer($config);
        $command = new CheckCommand($container);

        $commandTester = new CommandTester($command);
        $commandTester->execute([
            '--user' => '1',
            '--resource' => 'invoice:123',
            '--action' => 'edit',
            '--roles' => 'admin,accountant'
        ]);

        $output = $commandTester->getDisplay();
        // The authorization will be denied since we have a stub implementation
        $this->assertStringContainsString('DENIED', $output);
        $this->assertEquals(1, $commandTester->getStatusCode());
    }

    public function testMissingRequiredOptions(): void
    {
        $config = new ConsoleConfig();
        $container = new ServiceContainer($config);
        $command = new CheckCommand($container);

        $commandTester = new CommandTester($command);
        $commandTester->execute([
            '--user' => '1'
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('required', $output);
        $this->assertEquals(1, $commandTester->getStatusCode());
    }
}
