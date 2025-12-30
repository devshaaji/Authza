<?php

declare(strict_types=1);

namespace Authza\Adapters\Cache;

use Psr\SimpleCache\CacheInterface;
use Redis;

/**
 * RedisCache is a PSR-16 compliant Redis cache adapter
 */
class RedisCache implements CacheInterface
{
    private Redis $redis;

    /**
     * Create a new Redis cache instance
     *
     * @param Redis $redis Redis instance
     */
    public function __construct(Redis $redis)
    {
        $this->redis = $redis;
    }

    /**
     * @inheritDoc
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->redis->get($key);

        if ($value === false) {
            return $default;
        }

        return unserialize($value);
    }

    /**
     * @inheritDoc
     */
    public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
    {
        $serialized = serialize($value);

        if ($ttl === null) {
            return $this->redis->set($key, $serialized);
        }

        $seconds = 0;
        if ($ttl instanceof \DateInterval) {
            $now = new \DateTime();
            $expires = (new \DateTime())->add($ttl);
            $seconds = $expires->getTimestamp() - $now->getTimestamp();
        } elseif (is_int($ttl)) {
            $seconds = $ttl;
        }

        if ($seconds > 0) {
            return $this->redis->setex($key, $seconds, $serialized);
        }

        return $this->redis->set($key, $serialized);
    }

    /**
     * @inheritDoc
     */
    public function delete(string $key): bool
    {
        return $this->redis->del($key) > 0;
    }

    /**
     * @inheritDoc
     */
    public function clear(): bool
    {
        return $this->redis->flushDB();
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
        return $this->redis->exists($key) > 0;
    }
}
