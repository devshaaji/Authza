<?php

declare(strict_types=1);

namespace Authza\Console;

class ConsoleConfig
{
    /** @var array<string, mixed> */
    private array $config = [];

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
        $this->config = $this->mergeWithDefaults($config);
        $this->loadFromEnvironment();
    }

    /**
     * Create config from a file path
     *
     * @param string $path Path to config file
     * @return self
     * @throws \InvalidArgumentException If file not found or invalid
     */
    public static function fromFile(string $path): self
    {
        if (!file_exists($path)) {
            throw new \InvalidArgumentException("Config file not found: {$path}");
        }

        $config = require $path;

        if (!is_array($config)) {
            throw new \InvalidArgumentException("Config file must return an array");
        }

        return new self($config);
    }

    /**
     * Get default configuration values
     *
     * @return array<string, mixed>
     */
    private function getDefaults(): array
    {
        return [
            'cache' => [
                'instance' => null,  // PSR-16 CacheInterface instance (REQUIRED)
            ],
            'logger' => [
                'instance' => null,  // PSR-3 LoggerInterface instance (optional)
            ],
            'graph' => [
                'storage' => null,
            ],
            'policies' => [
                'register' => [],    // Explicit policy registration (secure)
            ],
            'dsl' => [
                'files' => [],       // Explicit DSL file list
            ],
            'bootstrap' => null,
        ];
    }

    /**
     * Merge user config with defaults
     *
     * @param array<string, mixed> $config User configuration
     * @return array<string, mixed>
     */
    private function mergeWithDefaults(array $config): array
    {
        $defaults = $this->getDefaults();
        return $this->arrayMergeRecursive($defaults, $config);
    }

    /**
     * Recursively merge arrays (user values override defaults)
     *
     * @param array<string, mixed> $defaults
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function arrayMergeRecursive(array $defaults, array $config): array
    {
        $result = $defaults;

        foreach ($config as $key => $value) {
            if (is_array($value) && isset($result[$key]) && is_array($result[$key])) {
                $result[$key] = $this->arrayMergeRecursive($result[$key], $value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Get a configuration value using dot notation
     *
     * @param string $key Configuration key (e.g., 'cache.adapter')
     * @param mixed $default Default value if key not found
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * Set a configuration value using dot notation
     *
     * @param string $key Configuration key
     * @param mixed $value Value to set
     * @return self
     */
    public function set(string $key, mixed $value): self
    {
        $keys = explode('.', $key);
        $config = &$this->config;

        foreach ($keys as $i => $k) {
            if ($i === count($keys) - 1) {
                $config[$k] = $value;
            } else {
                if (!isset($config[$k]) || !is_array($config[$k])) {
                    $config[$k] = [];
                }
                $config = &$config[$k];
            }
        }

        return $this;
    }

    /**
     * Get all configuration as array
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->config;
    }

    /**
     * Load configuration from environment variables
     * 
     * Note: Environment variables only support simple string values.
     * Cache must be provided via cache.instance in the config file.
     */
    private function loadFromEnvironment(): void
    {
        // Graph storage can be set via environment
        $graphStorage = getenv('AUTHZA_GRAPH_STORAGE');
        if ($graphStorage !== false && $graphStorage !== '') {
            $this->set('graph.storage', $graphStorage);
        }

        // Handle DSL files from environment (comma-separated)
        $dslFiles = getenv('AUTHZA_DSL_FILES');
        if ($dslFiles !== false && $dslFiles !== '') {
            $files = array_map('trim', explode(',', $dslFiles));
            $this->set('dsl.files', $files);
        }
    }
}
