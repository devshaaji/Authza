<?php

declare(strict_types=1);

namespace Authza\Core\Graph;

use Psr\SimpleCache\CacheInterface;

/**
 * Permission graph for precomputed permissions
 */
class PermissionGraph
{
    private array $graph = [];
    private ?CacheInterface $cache;

    public function __construct(?CacheInterface $cache = null)
    {
        $this->cache = $cache;
    }

    /**
     * Add a permission to the graph
     *
     * @param string $subject Subject identifier (e.g., "role:admin", "user:123")
     * @param string $resource Resource identifier (e.g., "invoice", "invoice:123")
     * @param string $action Action to perform (e.g., "create", "edit", "view")
     * @param string|null $condition Optional condition (e.g., "owner", "department==finance")
     */
    public function addPermission(string $subject, string $resource, string $action, ?string $condition = null): void
    {
        $key = $this->makeKey($subject, $resource, $action);
        
        if (!isset($this->graph[$key])) {
            $this->graph[$key] = [];
        }
        
        $this->graph[$key][] = [
            'subject' => $subject,
            'resource' => $resource,
            'action' => $action,
            'condition' => $condition,
        ];
    }

    /**
     * Check if a permission exists in the graph
     *
     * @param string $subject Subject identifier
     * @param string $resource Resource identifier
     * @param string $action Action to perform
     * @param array $context Optional context for condition evaluation
     * @return bool
     */
    public function hasPermission(string $subject, string $resource, string $action, array $context = []): bool
    {
        $key = $this->makeKey($subject, $resource, $action);
        
        if (!isset($this->graph[$key])) {
            // Try wildcard matching
            return $this->checkWildcardPermissions($subject, $resource, $action, $context);
        }
        
        foreach ($this->graph[$key] as $permission) {
            if ($this->evaluateCondition($permission['condition'], $context)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Build graph from DSL rules
     *
     * @param array $dslRules Array of DSL rule arrays
     * @return void
     */
    public function precomputeFromDsl(array $dslRules): void
    {
        foreach ($dslRules as $rule) {
            $this->addPermission(
                $rule['subject'],
                $rule['resource'],
                $rule['action'],
                $rule['condition'] ?? null
            );
        }
    }

    /**
     * Get all permissions in the graph
     *
     * @return array
     */
    public function getAllPermissions(): array
    {
        $permissions = [];
        
        foreach ($this->graph as $entries) {
            foreach ($entries as $entry) {
                $permissions[] = $entry;
            }
        }
        
        return $permissions;
    }

    /**
     * Clear all permissions from the graph
     */
    public function clear(): void
    {
        $this->graph = [];
        if ($this->cache) {
            $this->cache->clear();
        }
    }

    /**
     * Make a cache key from subject, resource, and action
     */
    private function makeKey(string $subject, string $resource, string $action): string
    {
        return "{$subject}:{$resource}:{$action}";
    }

    /**
     * Check permissions with wildcard matching
     */
    private function checkWildcardPermissions(string $subject, string $resource, string $action, array $context): bool
    {
        // Extract subject type and ID
        $subjectParts = explode(':', $subject, 2);
        $subjectType = $subjectParts[0] ?? '';
        
        // Check for wildcard subject (e.g., user:*)
        $wildcardSubject = "{$subjectType}:*";
        $wildcardKey = $this->makeKey($wildcardSubject, $resource, $action);
        
        if (isset($this->graph[$wildcardKey])) {
            foreach ($this->graph[$wildcardKey] as $permission) {
                if ($this->evaluateCondition($permission['condition'], $context)) {
                    return true;
                }
            }
        }
        
        // Check for wildcard resource (e.g., invoice:*)
        $resourceParts = explode(':', $resource, 2);
        $resourceType = $resourceParts[0] ?? '';
        $wildcardResource = "{$resourceType}:*";
        
        $wildcardResourceKey = $this->makeKey($subject, $wildcardResource, $action);
        
        if (isset($this->graph[$wildcardResourceKey])) {
            foreach ($this->graph[$wildcardResourceKey] as $permission) {
                if ($this->evaluateCondition($permission['condition'], $context)) {
                    return true;
                }
            }
        }
        
        // Also check resource type without ID (e.g., "invoice" when checking "invoice:123")
        if (isset($resourceParts[1])) {
            $resourceTypeKey = $this->makeKey($subject, $resourceType, $action);
            if (isset($this->graph[$resourceTypeKey])) {
                foreach ($this->graph[$resourceTypeKey] as $permission) {
                    if ($this->evaluateCondition($permission['condition'], $context)) {
                        return true;
                    }
                }
            }
        }
        
        return false;
    }

    /**
     * Evaluate a condition against context
     */
    private function evaluateCondition(?string $condition, array $context): bool
    {
        if ($condition === null) {
            return true;
        }
        
        // Handle "owner" condition
        if ($condition === 'owner') {
            return isset($context['is_owner']) && $context['is_owner'] === true;
        }
        
        // Handle equality conditions (e.g., department==finance)
        if (strpos($condition, '==') !== false) {
            [$key, $value] = explode('==', $condition, 2);
            $key = trim($key);
            $value = trim($value);
            return isset($context[$key]) && $context[$key] === $value;
        }
        
        // Handle inequality conditions (e.g., status!=paid)
        if (strpos($condition, '!=') !== false) {
            [$key, $value] = explode('!=', $condition, 2);
            $key = trim($key);
            $value = trim($value);
            return !isset($context[$key]) || $context[$key] !== $value;
        }
        
        // Unknown condition format - default to false for safety
        return false;
    }
}
