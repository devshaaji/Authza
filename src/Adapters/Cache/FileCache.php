<?php

declare(strict_types=1);

namespace Authza\Adapters\Cache;

use Psr\SimpleCache\CacheInterface;

/**
 * FileCache is a PSR-16 compliant file-based cache implementation
 */
class FileCache implements CacheInterface
{
    private string $cacheDir;

    /**
     * Create a new file cache instance
     *
     * @param string $cacheDir Directory to store cache files
     */
    public function __construct(string $cacheDir)
    {
        // Normalize path separators for the current OS
        $this->cacheDir = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $cacheDir), DIRECTORY_SEPARATOR);
        
        if (!is_dir($this->cacheDir)) {
            if (!mkdir($this->cacheDir, 0755, true) && !is_dir($this->cacheDir)) {
                throw new \RuntimeException("Failed to create cache directory: {$this->cacheDir}");
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $file = $this->getFilePath($key);

        if (!file_exists($file)) {
            return $default;
        }

        $data = unserialize(file_get_contents($file));

        if (isset($data['expiry']) && $data['expiry'] !== null && $data['expiry'] < time()) {
            $this->delete($key);
            return $default;
        }

        return $data['value'];
    }

    /**
     * @inheritDoc
     */
    public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
    {
        $expiry = null;
        
        if ($ttl instanceof \DateInterval) {
            $expiry = (new \DateTime())->add($ttl)->getTimestamp();
        } elseif (is_int($ttl) && $ttl > 0) {
            $expiry = time() + $ttl;
        }

        $data = [
            'value' => $value,
            'expiry' => $expiry,
        ];

        $file = $this->getFilePath($key);
        return file_put_contents($file, serialize($data)) !== false;
    }

    /**
     * @inheritDoc
     */
    public function delete(string $key): bool
    {
        $file = $this->getFilePath($key);
        
        if (file_exists($file)) {
            return unlink($file);
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function clear(): bool
    {
        $files = glob($this->cacheDir . DIRECTORY_SEPARATOR . '*');
        
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

    /**
     * @inheritDoc
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $results = [];
        foreach ($keys as $key) {
            $results[$key] = $this->get($key, $default);
        }
        return $results;
    }

    /**
     * @inheritDoc
     */
    public function setMultiple(iterable $values, null|int|\DateInterval $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            if (!$this->set($key, $value, $ttl)) {
                return false;
            }
        }
        return true;
    }

    /**
     * @inheritDoc
     */
    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            if (!$this->delete($key)) {
                return false;
            }
        }
        return true;
    }

    /**
     * @inheritDoc
     */
    public function has(string $key): bool
    {
        $file = $this->getFilePath($key);

        if (!file_exists($file)) {
            return false;
        }

        $data = unserialize(file_get_contents($file));

        if (isset($data['expiry']) && $data['expiry'] !== null && $data['expiry'] < time()) {
            $this->delete($key);
            return false;
        }

        return true;
    }

    /**
     * Get the file path for a cache key
     *
     * @param string $key Cache key
     * @return string File path
     */
    private function getFilePath(string $key): string
    {
        return $this->cacheDir . DIRECTORY_SEPARATOR . md5($key) . '.cache';
    }
}
