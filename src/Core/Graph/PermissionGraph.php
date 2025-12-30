<?php

declare(strict_types=1);

namespace Authza\Core\Graph;

use Psr\SimpleCache\CacheInterface;

/**
 * PermissionGraph manages precomputed permission lookups for fast authorization checks
 */
class PermissionGraph
{
    private const CACHE_KEY = 'authz:graph:v1';
    private const CACHE_TTL = 3600;

    private ?CacheInterface $cache;

    /**
     * @var array<string, bool>
     */
    private array $permissions = [];

    /**
     * Create a new permission graph
     *
     * @param CacheInterface|null $cache Optional cache for storing the graph
     */
    public function __construct(?CacheInterface $cache = null)
    {
        $this->cache = $cache;
        $this->loadFromCache();
    }

    /**
     * Precompute and store permissions in the graph
     *
     * @param array<array{subjectId: string|int, action: string, resourceType: string, resourceId: string|int, allowed: bool}> $permissions
     * @return void
     */
    public function precompute(array $permissions): void
    {
        $this->permissions = [];

        foreach ($permissions as $permission) {
            $key = $this->makeKey(
                (string)$permission['subjectId'],
                $permission['action'],
                $permission['resourceType'],
                (string)$permission['resourceId']
            );

            $this->permissions[$key] = $permission['allowed'];
        }

        $this->saveToCache();
    }

    /**
     * Check if a permission exists in the precomputed graph
     *
     * @param string $subjectId Subject identifier
     * @param string $action Action being performed
     * @param string $resourceType Resource type
     * @param string $resourceId Resource identifier
     * @return bool|null True if allowed, false if denied, null if not found
     */
    public function check(string $subjectId, string $action, string $resourceType, string $resourceId): ?bool
    {
        $key = $this->makeKey($subjectId, $action, $resourceType, $resourceId);
        
        if (!isset($this->permissions[$key])) {
            return null;
        }

        return $this->permissions[$key];
    }

    /**
     * Invalidate the permission graph cache
     *
     * @param string|null $subjectId Optional subject ID to invalidate specific entries
     * @return void
     */
    public function invalidate(?string $subjectId = null): void
    {
        if ($subjectId === null) {
            // Clear entire graph
            $this->permissions = [];
            
            if ($this->cache !== null) {
                $this->cache->delete(self::CACHE_KEY);
            }
        } else {
            // Remove entries for specific subject
            foreach (array_keys($this->permissions) as $key) {
                if (str_starts_with($key, $subjectId . ':')) {
                    unset($this->permissions[$key]);
                }
            }
            
            $this->saveToCache();
        }
    }

    /**
     * Make a cache key for a permission
     *
     * @param string $subjectId Subject identifier
     * @param string $action Action
     * @param string $resourceType Resource type
     * @param string $resourceId Resource identifier
     * @return string Cache key
     */
    private function makeKey(string $subjectId, string $action, string $resourceType, string $resourceId): string
    {
        return "{$subjectId}:{$action}:{$resourceType}:{$resourceId}";
    }

    /**
     * Load the graph from cache
     *
     * @return void
     */
    private function loadFromCache(): void
    {
        if ($this->cache === null) {
            return;
        }

        $cached = $this->cache->get(self::CACHE_KEY);
        
        if (is_array($cached)) {
            $this->permissions = $cached;
        }
    }

    /**
     * Save the graph to cache
     *
     * @return void
     */
    private function saveToCache(): void
    {
        if ($this->cache === null) {
            return;
        }

        $this->cache->set(self::CACHE_KEY, $this->permissions, self::CACHE_TTL);
    }
}
