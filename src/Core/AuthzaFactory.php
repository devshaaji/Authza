<?php

declare(strict_types=1);

namespace Authza\Core;

use Authza\Console\ConsoleConfig;
use Authza\Console\ServiceContainer;

/**
 * Factory for creating fully configured Authorization instances
 * 
 * This factory ensures both CLI and application code use the same
 * configuration and engine instance.
 * 
 * SECURITY: No auto-discovery - config must be explicitly provided.
 */
class AuthzaFactory
{
    /**
     * Create an Authorization instance from configuration array
     *
     * @param array<string, mixed> $config Configuration array
     * @return Authorization
     */
    public static function create(array $config): Authorization
    {
        $container = self::createServiceContainer($config);
        return $container->getAuthorization();
    }

    /**
     * Create an Authorization instance from a config file
     *
     * @param string $configPath Path to config file
     * @return Authorization
     * @throws \InvalidArgumentException If config file not found
     */
    public static function createFromFile(string $configPath): Authorization
    {
        if (!file_exists($configPath)) {
            throw new \InvalidArgumentException("Config file not found: {$configPath}");
        }
        
        $config = ConsoleConfig::fromFile($configPath);
        $container = new ServiceContainer($config);
        $container->bootstrap();
        return $container->getAuthorization();
    }

    /**
     * Create a ServiceContainer from configuration array
     *
     * @param array<string, mixed> $config Configuration array
     * @return ServiceContainer
     */
    public static function createServiceContainer(array $config): ServiceContainer
    {
        $consoleConfig = new ConsoleConfig($config);
        $container = new ServiceContainer($consoleConfig);
        $container->bootstrap();
        return $container;
    }
}
