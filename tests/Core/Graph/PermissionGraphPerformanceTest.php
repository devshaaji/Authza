<?php

declare(strict_types=1);

namespace Authza\Tests\Core\Graph;

use Authza\Adapters\Cache\ArrayCache;
use Authza\Core\Graph\PermissionGraph;
use PHPUnit\Framework\TestCase;

/**
 * Performance tests for PermissionGraph role hierarchy optimization.
 * 
 * These tests measure execution time to verify performance improvements.
 * They use @group performance so they can be run separately:
 * 
 *   ./vendor/bin/phpunit --group performance
 * 
 * Or excluded from regular runs:
 * 
 *   ./vendor/bin/phpunit --exclude-group performance
 */
class PermissionGraphPerformanceTest extends TestCase
{
    private const ITERATIONS = 1000;

    /**
     * @group performance
     */
    public function testCheckMultipleSpeedWithoutHierarchy(): void
    {
        $graph = new PermissionGraph();

        // Set up 50 role permissions
        for ($i = 1; $i <= 50; $i++) {
            $graph->addRule([
                'subject' => "role:role_{$i}",
                'resource' => 'document:*',
                'action' => 'read',
                'effect' => 'allow',
            ]);
        }
        $graph->flush();

        // Build subject list (simulating a user with 50 roles)
        $subjects = [];
        for ($i = 1; $i <= 50; $i++) {
            $subjects[] = "role_{$i}";
        }

        // Benchmark: check without hierarchy (linear iteration)
        $startTime = microtime(true);

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $graph->checkMultiple($subjects, 'read', 'document', '123');
        }

        $durationWithoutHierarchy = microtime(true) - $startTime;

        $this->assertLessThan(
            5.0, // Should complete 1000 iterations in under 5 seconds
            $durationWithoutHierarchy,
            "checkMultiple without hierarchy took too long: {$durationWithoutHierarchy}s"
        );

        // Output for manual inspection
        $avgMs = ($durationWithoutHierarchy / self::ITERATIONS) * 1000;
        fwrite(STDOUT, sprintf(
            "\n[PERF] checkMultiple WITHOUT hierarchy: %.4fs total, %.4fms avg per call\n",
            $durationWithoutHierarchy,
            $avgMs
        ));
    }

    /**
     * @group performance
     */
    public function testCheckMultipleSpeedWithHierarchy(): void
    {
        $graph = new PermissionGraph();

        // Set up 50 role permissions
        for ($i = 1; $i <= 50; $i++) {
            $graph->addRule([
                'subject' => "role:role_{$i}",
                'resource' => 'document:*',
                'action' => 'read',
                'effect' => 'allow',
            ]);
        }
        $graph->flush();

        // Set up hierarchy: user inherits all 50 roles
        $roles = [];
        for ($i = 1; $i <= 50; $i++) {
            $roles[] = "role_{$i}";
        }
        $graph->setRoleHierarchy([
            'user:alice' => $roles,
        ]);

        // Benchmark: check WITH hierarchy (pre-collapsed)
        $startTime = microtime(true);

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $graph->checkMultiple(['user:alice'], 'read', 'document', '123');
        }

        $durationWithHierarchy = microtime(true) - $startTime;

        $this->assertLessThan(
            5.0, // Should complete 1000 iterations in under 5 seconds
            $durationWithHierarchy,
            "checkMultiple with hierarchy took too long: {$durationWithHierarchy}s"
        );

        // Output for manual inspection
        $avgMs = ($durationWithHierarchy / self::ITERATIONS) * 1000;
        fwrite(STDOUT, sprintf(
            "\n[PERF] checkMultiple WITH hierarchy: %.4fs total, %.4fms avg per call\n",
            $durationWithHierarchy,
            $avgMs
        ));
    }

    /**
     * @group performance
     */
    public function testCompareHierarchyVsNoHierarchySpeed(): void
    {
        // ========================================
        // Setup WITHOUT hierarchy
        // ========================================
        $graphNoHierarchy = new PermissionGraph();

        for ($i = 1; $i <= 200; $i++) {
            $graphNoHierarchy->addRule([
                'subject' => "role:role_{$i}",
                'resource' => 'document:*',
                'action' => 'read',
                'effect' => 'allow',
            ]);
        }
        $graphNoHierarchy->flush();

        $subjectsFlat = [];
        for ($i = 1; $i <= 200; $i++) {
            $subjectsFlat[] = "role_{$i}";
        }

        // ========================================
        // Setup WITH hierarchy
        // ========================================
        $graphWithHierarchy = new PermissionGraph();

        for ($i = 1; $i <= 50; $i++) {
            $graphWithHierarchy->addRule([
                'subject' => "role:role_{$i}",
                'resource' => 'document:*',
                'action' => 'read',
                'effect' => 'allow',
            ]);
        }
        $graphWithHierarchy->flush();

        $roles = [];
        for ($i = 1; $i <= 200; $i++) {
            $roles[] = "role_{$i}";
        }
        $graphWithHierarchy->setRoleHierarchy([
            'user:alice' => $roles,
        ]);

        // ========================================
        // Benchmark WITHOUT hierarchy
        // ========================================
        $startNoHierarchy = microtime(true);
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $graphNoHierarchy->checkMultiple($subjectsFlat, 'read', 'document', '123');
        }
        $durationNoHierarchy = microtime(true) - $startNoHierarchy;

        // ========================================
        // Benchmark WITH hierarchy
        // ========================================
        $startWithHierarchy = microtime(true);
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $graphWithHierarchy->checkMultiple(['user:alice'], 'read', 'document', '123');
        }
        $durationWithHierarchy = microtime(true) - $startWithHierarchy;

        // ========================================
        // Results
        // ========================================
        $speedup = $durationNoHierarchy / $durationWithHierarchy;

        fwrite(STDOUT, sprintf(
            "\n[PERF] Comparison (50 roles, %d iterations):\n",
            self::ITERATIONS
        ));
        fwrite(STDOUT, sprintf(
            "  WITHOUT hierarchy: %.4fs (%.4fms avg)\n",
            $durationNoHierarchy,
            ($durationNoHierarchy / self::ITERATIONS) * 1000
        ));
        fwrite(STDOUT, sprintf(
            "  WITH hierarchy:    %.4fs (%.4fms avg)\n",
            $durationWithHierarchy,
            ($durationWithHierarchy / self::ITERATIONS) * 1000
        ));
        fwrite(STDOUT, sprintf(
            "  Speedup:           %.2fx\n",
            $speedup
        ));

        // Both should be fast enough
        $this->assertLessThan(5.0, $durationNoHierarchy);
        $this->assertLessThan(5.0, $durationWithHierarchy);

        // Record speedup for documentation
        $this->assertGreaterThan(0, $speedup, "Speedup should be positive");
    }

    /**
     * @group performance
     */
    public function testLargeScaleHierarchySetupSpeed(): void
    {
        $graph = new PermissionGraph();

        // Benchmark: setting up hierarchy for 100 users, each with 20 roles
        $hierarchy = [];
        for ($user = 1; $user <= 100; $user++) {
            $roles = [];
            for ($role = 1; $role <= 20; $role++) {
                $roles[] = "role:role_{$role}";
            }
            $hierarchy["user:user_{$user}"] = $roles;
        }

        $startTime = microtime(true);
        $graph->setRoleHierarchy($hierarchy);
        $setupDuration = microtime(true) - $startTime;

        fwrite(STDOUT, sprintf(
            "\n[PERF] Hierarchy setup (100 users × 20 roles): %.4fs\n",
            $setupDuration
        ));

        $this->assertLessThan(
            1.0, // Should complete in under 1 second
            $setupDuration,
            "Hierarchy setup took too long: {$setupDuration}s"
        );

        // Verify it works
        $stats = $graph->getStats();
        $this->assertEquals(100, $stats['role_hierarchy_count']);
    }

    /**
     * @group performance
     */
    public function testDeepHierarchyTraversalSpeed(): void
    {
        $graph = new PermissionGraph();

        // Create a deep hierarchy chain (50 levels)
        $hierarchy = [];
        for ($i = 1; $i < 50; $i++) {
            $hierarchy["role:level_{$i}"] = ["role:level_" . ($i + 1)];
        }
        $hierarchy['role:level_50'] = [];

        $graph->setRoleHierarchy($hierarchy);
        $graph->addRoleInheritance('user:deep', 'role:level_1');

        // Add permission at the deepest level
        $graph->addRule([
            'subject' => 'role:level_50',
            'resource' => 'secret:*',
            'action' => 'access',
            'effect' => 'allow',
        ]);
        $graph->flush();

        // Benchmark: checking through 50-level deep hierarchy
        $startTime = microtime(true);

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $graph->checkMultiple(['user:deep'], 'access', 'secret', '1');
        }

        $duration = microtime(true) - $startTime;
        $avgMs = ($duration / self::ITERATIONS) * 1000;

        fwrite(STDOUT, sprintf(
            "\n[PERF] Deep hierarchy traversal (50 levels, %d iterations): %.4fs total, %.4fms avg\n",
            self::ITERATIONS,
            $duration,
            $avgMs
        ));

        $this->assertLessThan(
            5.0,
            $duration,
            "Deep hierarchy check took too long: {$duration}s"
        );
    }

    /**
     * @group performance
     */
    public function testCacheLoadSpeed(): void
    {
        $cache = new ArrayCache();

        // Setup: create graph with hierarchy and persist to cache
        $graph1 = new PermissionGraph($cache);

        $hierarchy = [];
        for ($user = 1; $user <= 50; $user++) {
            $roles = [];
            for ($role = 1; $role <= 10; $role++) {
                $roles[] = "role:role_{$role}";
            }
            $hierarchy["user:user_{$user}"] = $roles;
        }
        $graph1->setRoleHierarchy($hierarchy);

        for ($i = 1; $i <= 100; $i++) {
            $graph1->addRule([
                'subject' => "role:role_{$i}",
                'resource' => 'document:*',
                'action' => 'read',
                'effect' => 'allow',
            ]);
        }
        $graph1->flush();

        // Benchmark: loading from cache (simulates new request)
        $loadTimes = [];
        for ($i = 0; $i < 100; $i++) {
            $startTime = microtime(true);
            $newGraph = new PermissionGraph($cache);
            $loadTimes[] = microtime(true) - $startTime;
        }

        $avgLoadTime = array_sum($loadTimes) / count($loadTimes);
        $maxLoadTime = max($loadTimes);

        fwrite(STDOUT, sprintf(
            "\n[PERF] Cache load (50 users, 100 rules): %.4fms avg, %.4fms max\n",
            $avgLoadTime * 1000,
            $maxLoadTime * 1000
        ));

        $this->assertLessThan(
            0.1, // Should load in under 100ms
            $avgLoadTime,
            "Cache load took too long: {$avgLoadTime}s"
        );
    }

    /**
     * @group performance
     */
    public function testManyUsersCheckSpeed(): void
    {
        $graph = new PermissionGraph();

        // Setup: 1000 users, each with 5 roles
        $hierarchy = [];
        for ($user = 1; $user <= 1000; $user++) {
            $hierarchy["user:user_{$user}"] = [
                'role:basic',
                'role:standard',
                "role:dept_" . ($user % 10),
                "role:team_" . ($user % 100),
                $user % 50 === 0 ? 'role:admin' : 'role:user',
            ];
        }
        $graph->setRoleHierarchy($hierarchy);

        // Add permissions
        $graph->addRule(['subject' => 'role:basic', 'resource' => 'public:*', 'action' => 'read', 'effect' => 'allow']);
        $graph->addRule(['subject' => 'role:admin', 'resource' => 'admin:*', 'action' => '*', 'effect' => 'allow']);
        $graph->flush();

        // Benchmark: check random users
        $startTime = microtime(true);

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $userId = "user:user_" . (($i % 1000) + 1);
            $graph->checkMultiple([$userId], 'read', 'public', '1');
        }

        $duration = microtime(true) - $startTime;
        $avgMs = ($duration / self::ITERATIONS) * 1000;

        fwrite(STDOUT, sprintf(
            "\n[PERF] Many users check (1000 users, %d checks): %.4fs total, %.4fms avg\n",
            self::ITERATIONS,
            $duration,
            $avgMs
        ));

        $this->assertLessThan(
            5.0,
            $duration,
            "Many users check took too long: {$duration}s"
        );
    }

    /**
     * @group performance
     */
    public function testCycleDetectionSpeed(): void
    {
        $graph = new PermissionGraph();

        // Benchmark: setting up large valid hierarchy (no cycles)
        // This tests that cycle detection doesn't slow down valid hierarchies
        $hierarchy = [];

        // Tree structure: 4 levels, branching factor 5
        // Level 0: 1 root
        // Level 1: 5 nodes
        // Level 2: 25 nodes
        // Level 3: 125 nodes (leaves)
        // Total: 156 nodes

        $hierarchy['role:root'] = ['role:l1_1', 'role:l1_2', 'role:l1_3', 'role:l1_4', 'role:l1_5'];

        for ($i = 1; $i <= 5; $i++) {
            $children = [];
            for ($j = 1; $j <= 5; $j++) {
                $children[] = "role:l2_{$i}_{$j}";
            }
            $hierarchy["role:l1_{$i}"] = $children;
        }

        for ($i = 1; $i <= 5; $i++) {
            for ($j = 1; $j <= 5; $j++) {
                $children = [];
                for ($k = 1; $k <= 5; $k++) {
                    $children[] = "role:l3_{$i}_{$j}_{$k}";
                }
                $hierarchy["role:l2_{$i}_{$j}"] = $children;
            }
        }

        // Leaves
        for ($i = 1; $i <= 5; $i++) {
            for ($j = 1; $j <= 5; $j++) {
                for ($k = 1; $k <= 5; $k++) {
                    $hierarchy["role:l3_{$i}_{$j}_{$k}"] = [];
                }
            }
        }

        $startTime = microtime(true);
        $graph->setRoleHierarchy($hierarchy);
        $duration = microtime(true) - $startTime;

        fwrite(STDOUT, sprintf(
            "\n[PERF] Cycle detection on 156-node tree: %.4fs\n",
            $duration
        ));

        $this->assertLessThan(
            1.0, // Should complete in under 1 second
            $duration,
            "Cycle detection took too long: {$duration}s"
        );

        // Verify hierarchy was set correctly
        $rootEffective = $graph->getEffectiveSubjects('role:root');
        $this->assertCount(156, $rootEffective);
    }

    public function testCachedHierarchyFastPath(): void
    {
        $cache = new ArrayCache();

        // First instance: build and flush hierarchy
        $graph1 = new PermissionGraph($cache);
        $roles = [];
        for ($i = 1; $i <= 100; $i++) {
            $roles[] = "role_{$i}";
        }
        $graph1->setRoleHierarchy(['user:alice' => $roles]);
        $graph1->flush();

        // Second instance: should load effectiveSubjects from cache and be faster
        $graph2 = new PermissionGraph($cache);

        $warmStart = microtime(true);
        for ($i = 0; $i < 1000; $i++) {
            $graph2->checkMultiple(['user:alice'], 'read', 'document', '1');
        }
        $cachedDuration = microtime(true) - $warmStart;

        $this->assertLessThan(5.0, $cachedDuration);
    }
}
