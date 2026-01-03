<?php

declare(strict_types=1);

namespace Authza\Adapters\Cache;

use Psr\SimpleCache\CacheInterface;

class FileCache implements CacheInterface
{
    private string $cacheDir;
    private int $defaultTtl;

    public function __construct(string $cacheDir, int $defaultTtl = 3600)
    {
        $this->cacheDir = rtrim($cacheDir, DIRECTORY_SEPARATOR);
        $this->defaultTtl = $defaultTtl;

        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $file = $this->getFilePath($key);

        if (!file_exists($file)) {
            return $default;
        }

        $content = file_get_contents($file);
        if ($content === false) {
            return $default;
        }

        // Use JSON instead of unserialize for safety
        $data = json_decode($content, true);
        if (!is_array($data) || !isset($data['value'], $data['expiry'])) {
            $this->delete($key);
            return $default;
        }

        // Check expiry
        if ($data['expiry'] !== 0 && $data['expiry'] < time()) {
            $this->delete($key);
            return $default;
        }

        return $data['value'];
    }

    public function set(string $key, mixed $value, \DateInterval|int|null $ttl = null): bool
    {
        // Only allow JSON-serializable values (no objects)
        if (is_object($value) || is_resource($value)) {
            return false;
        }

        $file = $this->getFilePath($key);
        $expiry = $this->calculateExpiry($ttl);

        $data = [
            'value' => $value,
            'expiry' => $expiry,
        ];

        // Use JSON for safe serialization
        $content = json_encode($data, JSON_THROW_ON_ERROR);
        
        return file_put_contents($file, $content, LOCK_EX) !== false;
    }

    public function delete(string $key): bool
    {
        $file = $this->getFilePath($key);

        if (file_exists($file)) {
            return unlink($file);
        }

        return true;
    }

    public function clear(): bool
    {
        $files = glob($this->cacheDir . DIRECTORY_SEPARATOR . '*.cache');
        
        if ($files === false) {
            return false;
        }

        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        return true;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }
        return $result;
    }

    public function setMultiple(iterable $values, \DateInterval|int|null $ttl = null): bool
    {
        $success = true;
        foreach ($values as $key => $value) {
            if (!$this->set($key, $value, $ttl)) {
                $success = false;
            }
        }
        return $success;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        $success = true;
        foreach ($keys as $key) {
            if (!$this->delete($key)) {
                $success = false;
            }
        }
        return $success;
    }

    public function has(string $key): bool
    {
        return $this->get($key, $this) !== $this;
    }

    private function getFilePath(string $key): string
    {
        $hash = md5($key);
        return $this->cacheDir . DIRECTORY_SEPARATOR . $hash . '.cache';
    }

    private function calculateExpiry(\DateInterval|int|null $ttl): int
    {
        if ($ttl === null) {
            return time() + $this->defaultTtl;
        }

        if ($ttl instanceof \DateInterval) {
            return time() + (int) (new \DateTime())->add($ttl)->getTimestamp() - time();
        }

        if ($ttl === 0) {
            return 0;
        }

        return time() + $ttl;
    }
}