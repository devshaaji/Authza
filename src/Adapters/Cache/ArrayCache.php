<?php

declare(strict_types=1);

namespace Authza\Adapters\Cache;

use Psr\SimpleCache\CacheInterface;

/**
 * ArrayCache is a PSR-16 compliant in-memory cache implementation
 */
class ArrayCache implements CacheInterface
{
    /**
     * @var array<string, array{value: mixed, expiry: int|null}>
     */
    private array $cache = [];

    /**
     * @inheritDoc
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->has($key)) {
            return $default;
        }

        return $this->cache[$key]['value'];
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

        $this->cache[$key] = [
            'value' => $value,
            'expiry' => $expiry,
        ];

        return true;
    }

    /**
     * @inheritDoc
     */
    public function delete(string $key): bool
    {
        unset($this->cache[$key]);
        return true;
    }

    /**
     * @inheritDoc
     */
    public function clear(): bool
    {
        $this->cache = [];
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
            $this->set($key, $value, $ttl);
        }
        return true;
    }

    /**
     * @inheritDoc
     */
    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }
        return true;
    }

    /**
     * @inheritDoc
     */
    public function has(string $key): bool
    {
        if (!isset($this->cache[$key])) {
            return false;
        }

        $expiry = $this->cache[$key]['expiry'];
        if ($expiry !== null && $expiry < time()) {
            unset($this->cache[$key]);
            return false;
        }

        return true;
    }
}
