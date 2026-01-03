<?php

declare(strict_types=1);

namespace Authza\Core\Graph;

use Authza\Exceptions\RoleHierarchyCycleException;
use Authza\Match\SubjectSpecificity;
use Psr\SimpleCache\CacheInterface;

/**
 * PermissionGraph manages precomputed permission lookups for fast authorization checks
 */
class PermissionGraph
{
    private const CACHE_KEY = 'authz:graph:v1';
    private const CACHE_TTL = 3600;

    private const EFFECTIVE_SUBJECTS_CACHE_KEY = 'authz:graph:effective-subjects:v1';

    private ?CacheInterface $cache;

    /**
     * @var array<string, bool>
     */
    private array $permissions = [];

    /**
     * @var array<string, array{subject: string, resource: string, action: string, condition: mixed, effect: string}>
     */
    private array $rules = [];

    /**
     * Pre-collapsed role hierarchy cache.
     *
     * Key is a subject (typically a user subject id). Value is the full set of
     * effective subjects including the subject itself and its inherited roles.
     *
     * @var array<string, array<int, string>>
     */
    private array $effectiveSubjects = [];

    /**
     * Direct inheritance adjacency list (not collapsed).
     *
     * @var array<string, array<int, string>>
     */
    private array $roleHierarchyEdges = [];

    /**
     * Track if changes need to be persisted
     */
    private bool $dirty = false;

    /**
     * Track if effective subject hierarchy changes need to be persisted
     */
    private bool $effectiveSubjectsDirty = false;

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
     * Optimized: Uses prioritized lookup order for most common patterns
     * Time complexity: O(1) to O(6) hash lookups
     *
     * @param string $subjectId Subject identifier
     * @param string $action Action being performed
     * @param string $resourceType Resource type
     * @param string $resourceId Resource identifier
     * @return bool|null True if allowed, false if denied, null if not found
     */
    public function check(string $subjectId, string $action, string $resourceType, string $resourceId): ?bool
    {
        // Optimized key construction - avoid repeated string concatenation
        $baseKey = "{$subjectId}:{$action}:{$resourceType}";
        
        // Most common: exact match or type-level wildcard (covers 90%+ of cases)
        return $this->permissions["{$baseKey}:{$resourceId}"]
            ?? $this->permissions["{$baseKey}:*"]
            // Less common: subject wildcards
            ?? $this->permissions["*:{$action}:{$resourceType}:{$resourceId}"]
            ?? $this->permissions["*:{$action}:{$resourceType}:*"]
            // Rare: action wildcards (superadmin patterns)
            ?? $this->permissions["{$subjectId}:*:{$resourceType}:{$resourceId}"]
            ?? $this->permissions["{$subjectId}:*:{$resourceType}:*"]
            ?? null;
    }

    /**
     * Batch check permissions for multiple roles at once
     *
     * If a role hierarchy has been configured, subject IDs are expanded using the
     * pre-collapsed hierarchy for each supplied subject.
     *
     * @param array<string> $subjectIds Array of subject IDs (user ID + roles)
     * @param string $action Action being performed
     * @param string $resourceType Resource type
     * @param string $resourceId Resource identifier
     * @return bool|null True if any allows, false if any denies (deny wins), null if not found
     */
    public function checkMultiple(array $subjectIds, string $action, string $resourceType, string $resourceId): ?bool
    {
        // Fast path: no hierarchy configured.
        if ($this->effectiveSubjects === []) {
            $allowed = null;

            foreach ($subjectIds as $subjectId) {
                $result = $this->check($subjectId, $action, $resourceType, $resourceId);

                // Deny takes immediate precedence - early exit
                if ($result === false) {
                    return false;
                }

                if ($result === true) {
                    $allowed = true;
                }
            }

            return $allowed;
        }

        // With hierarchy: expand to effective subject set once, then check.
        // Keep the full subject identifiers (e.g., "role:staff") to match how addRule() stores permissions.
        $expanded = [];
        foreach ($subjectIds as $subjectId) {
            foreach ($this->getEffectiveSubjects($subjectId) as $effective) {
                $expanded[$effective] = true;
            }
        }

        $allowed = null;
        foreach (SubjectSpecificity::sortBySpecificity(array_keys($expanded)) as $subjectId) {
            $result = $this->check((string) $subjectId, $action, $resourceType, $resourceId);

            if ($result === false) {
                return false;
            }

            if ($result === true) {
                $allowed = true;
            }
        }

        return $allowed;
    }

    /**
     * Configure role hierarchy in bulk.
     *
     * Expected format:
     * [
     *   'user:42' => ['role:staff', 'role:finance'],
     *   'role:manager' => ['role:staff'],
     * ]
     *
     * Internally stored as effective subjects arrays including the key subject.
     *
     * @param array<string, array<int, string>> $hierarchy
     */
    public function setRoleHierarchy(array $hierarchy): void
    {
        // Build adjacency list first and validate cycles before mutating/committing state.
        $edges = [];
        foreach ($hierarchy as $subject => $inheritedSubjects) {
            $edges[$subject] = array_values(array_unique(array_filter($inheritedSubjects, static fn ($s) => is_string($s) && $s !== '')));
        }

        $this->assertNoRoleHierarchyCycles($edges);

        $this->roleHierarchyEdges = $edges;
        $this->effectiveSubjects = [];

        foreach ($hierarchy as $subject => $inheritedSubjects) {
            $this->addRoleInheritance($subject, $inheritedSubjects);
        }

        $this->saveEffectiveSubjectsToCache();
    }

    /**
     * Add role inheritance for a subject.
     *
     * @param string $subject
     * @param string|array<int, string> $inheritedSubjects
     */
    public function addRoleInheritance(string $subject, string|array $inheritedSubjects): void
    {
        $queue = is_array($inheritedSubjects) ? array_values($inheritedSubjects) : [$inheritedSubjects];

        // Update adjacency list for cycle detection.
        $this->roleHierarchyEdges[$subject] ??= [];
        foreach ($queue as $inherited) {
            if (!is_string($inherited) || $inherited === '') {
                continue;
            }
            $this->roleHierarchyEdges[$subject][] = $inherited;
        }
        $this->roleHierarchyEdges[$subject] = array_values(array_unique($this->roleHierarchyEdges[$subject]));

        // Validate that the new edge(s) didn't introduce a cycle.
        $this->assertNoRoleHierarchyCycles($this->roleHierarchyEdges);

        if (!isset($this->effectiveSubjects[$subject])) {
            $this->effectiveSubjects[$subject] = [$subject];
        } elseif (!in_array($subject, $this->effectiveSubjects[$subject], true)) {
            $this->effectiveSubjects[$subject][] = $subject;
        }

        // Build transitive effective subjects using BFS while avoiding duplicates.
        // We need to follow both effectiveSubjects (already computed) AND roleHierarchyEdges (graph structure).
        $seen = array_fill_keys($this->effectiveSubjects[$subject], true);

        while ($queue !== []) {
            $current = array_shift($queue);
            if (!is_string($current) || $current === '') {
                continue;
            }

            if (isset($seen[$current])) {
                continue;
            }

            $seen[$current] = true;
            $this->effectiveSubjects[$subject][] = $current;

            // Follow the graph edges to get transitive inheritance
            if (isset($this->roleHierarchyEdges[$current])) {
                foreach ($this->roleHierarchyEdges[$current] as $child) {
                    if (!isset($seen[$child])) {
                        $queue[] = $child;
                    }
                }
            }

            // Also include any pre-computed effective subjects
            if (isset($this->effectiveSubjects[$current])) {
                foreach ($this->effectiveSubjects[$current] as $child) {
                    if (!isset($seen[$child])) {
                        $queue[] = $child;
                    }
                }
            }
        }

        $this->effectiveSubjectsDirty = true;
    }

    /**
     * Get the effective subjects for a subject.
     *
     * @param string $subject
     * @return array<int, string>
     */
    public function getEffectiveSubjects(string $subject): array
    {
        return $this->effectiveSubjects[$subject] ?? [$subject];
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
            $this->rules = [];
            $this->effectiveSubjects = [];
            $this->roleHierarchyEdges = [];

            if ($this->cache !== null) {
                $this->cache->delete(self::CACHE_KEY);
                $this->cache->delete(self::EFFECTIVE_SUBJECTS_CACHE_KEY);
            }

            return;
        }

        foreach (array_keys($this->permissions) as $key) {
            if (str_starts_with($key, $subjectId . ':')) {
                unset($this->permissions[$key]);
                unset($this->rules[$key]);
            }
        }

        unset($this->effectiveSubjects[$subjectId]);
        unset($this->roleHierarchyEdges[$subjectId]);

        // Also remove as a child edge to prevent dangling references.
        foreach ($this->roleHierarchyEdges as $parent => $children) {
            $this->roleHierarchyEdges[$parent] = array_values(array_filter($children, static fn (string $c) => $c !== $subjectId));
        }

        $this->saveToCache();
        $this->saveEffectiveSubjectsToCache();
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
     * Note: Call flush() after batch additions to persist to cache
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
        $condition = $rule['condition'] ?? null;

        // Keep the full subject identifier (e.g., "role:developer" or "user:42")
        // to avoid collision between user IDs and role names
        $subjectId = $subject;

        $resourceParts = explode(':', $resource, 2);
        $resourceType = $resourceParts[0];
        $resourceId = $resourceParts[1] ?? '*';

        $key = $this->makeKey($subjectId, $action, $resourceType, $resourceId);
        $this->permissions[$key] = ($effect === 'allow');
        
        // Store the original rule for export
        $this->rules[$key] = [
            'subject' => $subject,
            'resource' => $resource,
            'action' => $action,
            'condition' => $condition,
            'effect' => $effect,
        ];

        // Mark as dirty instead of saving immediately
        $this->dirty = true;
    }

    /**
     * Flush pending changes to cache
     * 
     * Call this after batch operations like importing multiple rules
     *
     * @return void
     */
    public function flush(): void
    {
        if ($this->dirty) {
            $this->saveToCache();
            $this->dirty = false;
        }

        if ($this->effectiveSubjectsDirty) {
            $this->saveEffectiveSubjectsToCache();
            $this->effectiveSubjectsDirty = false;
        }
    }

    /**
     * Get statistics about the permission graph
     *
     * @return array{total_rules: int, by_resource_type: array<string, int>, by_subject_type: array<string, int>, role_hierarchy_count: int}
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
            'role_hierarchy_count' => count($this->effectiveSubjects),
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

        $cachedEffectiveSubjects = $this->cache->get(self::EFFECTIVE_SUBJECTS_CACHE_KEY);
        if (is_array($cachedEffectiveSubjects)) {
            $this->effectiveSubjects = $cachedEffectiveSubjects;
        }

        // roleHierarchyEdges is intentionally not loaded because it's derivable from upstream sources.
        // Cycle safety is enforced on writes.
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
     * Save effective subjects to cache
     */
    private function saveEffectiveSubjectsToCache(): void
    {
        if ($this->cache === null) {
            return;
        }

        $this->cache->set(self::EFFECTIVE_SUBJECTS_CACHE_KEY, $this->effectiveSubjects, self::CACHE_TTL);
    }

    /**
     * Detect cycles in a directed role hierarchy graph.
     *
     * Uses DFS with a recursion stack ("visiting") to catch back-edges.
     * Throws RoleHierarchyCycleException with a concrete cycle chain.
     *
     * @param array<string, array<int, string>> $edges
     */
    private function assertNoRoleHierarchyCycles(array $edges): void
    {
        $visited = [];
        $visiting = [];
        $stack = [];

        foreach (array_keys($edges) as $node) {
            if (isset($visited[$node])) {
                continue;
            }

            $this->dfsAssertNoCycle($node, $edges, $visited, $visiting, $stack);
        }
    }

    /**
     * @param array<string, array<int, string>> $edges
     * @param array<string, bool> $visited
     * @param array<string, bool> $visiting
     * @param array<int, string> $stack
     */
    private function dfsAssertNoCycle(string $node, array $edges, array &$visited, array &$visiting, array &$stack): void
    {
        $visiting[$node] = true;
        $stack[] = $node;

        foreach ($edges[$node] ?? [] as $next) {
            if (!isset($edges[$next])) {
                // Leaf / unknown node - cannot contribute a cycle unless it points back, which will be represented as its own key.
                continue;
            }

            if (isset($visiting[$next])) {
                // Build cycle chain from first occurrence of $next in the stack up to end, plus $next again.
                $idx = array_search($next, $stack, true);
                $cycle = $idx === false ? [$next, $node, $next] : array_merge(array_slice($stack, $idx), [$next]);
                throw new RoleHierarchyCycleException($cycle);
            }

            if (!isset($visited[$next])) {
                $this->dfsAssertNoCycle($next, $edges, $visited, $visiting, $stack);
            }
        }

        array_pop($stack);
        unset($visiting[$node]);
        $visited[$node] = true;
    }

    /**
     * Get all rules in the permission graph
     *
     * @return array<array<string, mixed>> Array of rule arrays with subject, resource, action, condition keys
     */
    public function getRules(): array
    {
        return array_values($this->rules);
    }

    /**
     * Precompute permissions from DSL rules (for DSL support)
     * 
     * Optimized: Batches cache writes
     *
     * @param array $dslRules Array of DSL rule arrays
     * @return void
     */
    public function precomputeFromDsl(array $dslRules): void
    {
        foreach ($dslRules as $rule) {
            $this->addRule($rule);
        }
        
        // Single cache write after all rules added
        $this->flush();
    }

    /**
     * Ensure changes are persisted on destruction
     */
    public function __destruct()
    {
        $this->flush();
    }
}
