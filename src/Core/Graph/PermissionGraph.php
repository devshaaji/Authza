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
           
            $this->permissions = [];
            
            if ($this->cache !== null) {
                $this->cache->delete(self::CACHE_KEY);
            }
        } else {
            foreach (array_keys($this->permissions) as $key) {
                if (str_starts_with($key, $subjectId . ':')) {
                    unset($this->permissions[$key]);
                }
            }
            
            $this->saveToCache();
        }
    }

    /**
     * Clear the permission graph (alias for invalidate with no arguments)
     *
     * @param string|null $subjectId Optional subject ID to clear specific entries
     * @return void
     */
    public function clear(?string $subjectId = null): void
    {
        $this->invalidate($subjectId);
    }

    /**
     * Add a single rule to the permission graph
     *
     * @param array{subject: string, resource: string, action: string, effect?: string, condition?: mixed} $rule
     * @return void
     */
    public function addRule(array $rule): void
    {
        $subject = $rule['subject'];
        $resource = $rule['resource'];
        $action = $rule['action'];
        $effect = $rule['effect'] ?? 'allow';

       
        $subjectParts = explode(':', $subject, 2);
        $subjectId = $subjectParts[1] ?? $subject;

        $resourceParts = explode(':', $resource, 2);
        $resourceType = $resourceParts[0];
        $resourceId = $resourceParts[1] ?? '*';

        $key = $this->makeKey($subjectId, $action, $resourceType, $resourceId);
        $this->permissions[$key] = ($effect === 'allow');

        $this->saveToCache();
    }

    /**
     * Get statistics about the permission graph
     *
     * @return array{total_rules: int, by_resource_type: array<string, int>, by_subject_type: array<string, int>}
     */
    public function getStats(): array
    {
        $byResourceType = [];
        $bySubjectType = [];

        foreach ($this->permissions as $key => $allowed) {
    
            $parts = explode(':', $key, 4);

            if (count($parts) === 4) {
                $resourceType = $parts[2];
                $subjectId = $parts[0];

                if (!isset($byResourceType[$resourceType])) {
                    $byResourceType[$resourceType] = 0;
                }
                $byResourceType[$resourceType]++;

                if (!isset($bySubjectType[$subjectId])) {
                    $bySubjectType[$subjectId] = 0;
                }
                $bySubjectType[$subjectId]++;
            }
        }

        return [
            'total_rules' => count($this->permissions),
            'by_resource_type' => $byResourceType,
            'by_subject_type' => $bySubjectType,
        ];
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

    /**
     * Get all rules in the permission graph
     *
     * @return array<array<string, mixed>> Array of rule arrays with subject, resource, action, condition keys
     */
    public function getRules(): array
    {
        $rules = [];
        
        foreach ($this->permissions as $key => $allowed) {
            // Parse key format: subjectId:action:resourceType:resourceId
            $parts = explode(':', $key, 4);
            
            if (count($parts) === 4) {
                $rules[] = [
                    'subject' => $parts[0],
                    'resource' => $parts[2] . ($parts[3] !== '*' ? ':' . $parts[3] : ''),
                    'action' => $parts[1],
                    'condition' => null,
                    'effect' => $allowed ? 'allow' : 'deny',
                ];
            }
        }
        
        return $rules;
    }

    /**
     * Precompute permissions from DSL rules (for DSL support)
     * 
     * DSL rules format: [['subject' => 'role:admin', 'resource' => 'invoice', 'action' => 'create', 'condition' => null, 'effect' => 'allow'], ...]
     *
     * @param array $dslRules Array of DSL rule arrays
     * @return void
     */
    public function precomputeFromDsl(array $dslRules): void
    {
        foreach ($dslRules as $rule) {
            $subject = $rule['subject'];
            $resource = $rule['resource'];
            $action = $rule['action'];
            $effect = $rule['effect'] ?? 'allow'; // Default to 'allow' if not specified
            
            // Extract subject ID from DSL format (e.g., "role:admin" -> "admin", "user:123" -> "123")
            $subjectParts = explode(':', $subject, 2);
            $subjectId = $subjectParts[1] ?? $subject;
            
            // Extract resource type and ID (e.g., "invoice:123" -> type:"invoice", id:"123")
            $resourceParts = explode(':', $resource, 2);
            $resourceType = $resourceParts[0];
            $resourceId = $resourceParts[1] ?? '*'; // Use * as wildcard for resource type
            
            $key = $this->makeKey($subjectId, $action, $resourceType, $resourceId);
            // Respect effect field: true for 'allow', false for 'deny'
            $this->permissions[$key] = ($effect === 'allow');
        }
        
        $this->saveToCache();
    }
}
