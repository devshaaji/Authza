<?php

declare(strict_types=1);

namespace Authza\Adapters\Cache;

use Psr\SimpleCache\CacheInterface;

/**
 * Simple array-based cache implementation for testing and development
 */
class ArrayCache implements CacheInterface
{
    private array $cache = [];
    private array $ttls = [];

    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->has($key)) {
            return $default;
        }
        
        return $this->cache[$key];
    }

    public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
    {
        $this->cache[$key] = $value;
        
        if ($ttl !== null) {
            $seconds = $ttl instanceof \DateInterval 
                ? (new \DateTime())->add($ttl)->getTimestamp() - time()
                : $ttl;
            $this->ttls[$key] = time() + $seconds;
        }
        
        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->cache[$key], $this->ttls[$key]);
        return true;
    }

    public function clear(): bool
    {
        $this->cache = [];
        $this->ttls = [];
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

    public function setMultiple(iterable $values, null|int|\DateInterval $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }
        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }
        return true;
    }

    public function has(string $key): bool
    {
        if (!isset($this->cache[$key])) {
            return false;
        }
        
        // Check TTL
        if (isset($this->ttls[$key]) && $this->ttls[$key] < time()) {
            $this->delete($key);
            return false;
        }
        
        return true;
    }
}
