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
        $this->config = $config;
        $this->loadFromEnvironment();
    }

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
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    private function loadFromEnvironment(): void
    {
        if ($adapter = getenv('AUTHZA_CACHE_ADAPTER')) {
            $this->config['cache']['adapter'] = $adapter;
        }

        if ($path = getenv('AUTHZA_CACHE_PATH')) {
            $this->config['cache']['path'] = $path;
        }

        if ($storage = getenv('AUTHZA_GRAPH_STORAGE')) {
            $this->config['graph']['storage'] = $storage;
        }

        if ($namespace = getenv('AUTHZA_POLICY_NAMESPACE')) {
            $this->config['policies']['namespace'] = $namespace;
        }
    }
}
