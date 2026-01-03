<?php

declare(strict_types=1);

namespace Authza\Tests\Core\Graph;

use Authza\Adapters\Cache\ArrayCache;
use Authza\Core\Graph\PermissionGraph;
use Authza\Exceptions\RoleHierarchyCycleException;
use PHPUnit\Framework\TestCase;

class PermissionGraphTest extends TestCase
{
    public function testPrecomputeAndCheck(): void
    {
        $graph = new PermissionGraph();

        $permissions = [
            ['subjectId' => '1', 'action' => 'edit', 'resourceType' => 'invoice', 'resourceId' => '100', 'allowed' => true],
            ['subjectId' => '1', 'action' => 'delete', 'resourceType' => 'invoice', 'resourceId' => '100', 'allowed' => false],
            ['subjectId' => '2', 'action' => 'view', 'resourceType' => 'user', 'resourceId' => '5', 'allowed' => true],
        ];

        $graph->precompute($permissions);

        $this->assertTrue($graph->check('1', 'edit', 'invoice', '100'));
        $this->assertFalse($graph->check('1', 'delete', 'invoice', '100'));
        $this->assertTrue($graph->check('2', 'view', 'user', '5'));
    }

    public function testCheckReturnsNullForNonExistentPermission(): void
    {
        $graph = new PermissionGraph();

        $this->assertNull($graph->check('1', 'edit', 'invoice', '100'));
    }

    public function testInvalidateAll(): void
    {
        $graph = new PermissionGraph();

        $permissions = [
            ['subjectId' => '1', 'action' => 'edit', 'resourceType' => 'invoice', 'resourceId' => '100', 'allowed' => true],
        ];

        $graph->precompute($permissions);
        $this->assertTrue($graph->check('1', 'edit', 'invoice', '100'));

        $graph->invalidate();
        $this->assertNull($graph->check('1', 'edit', 'invoice', '100'));
    }

    public function testInvalidateSpecificSubject(): void
    {
        $graph = new PermissionGraph();

        $permissions = [
            ['subjectId' => '1', 'action' => 'edit', 'resourceType' => 'invoice', 'resourceId' => '100', 'allowed' => true],
            ['subjectId' => '2', 'action' => 'view', 'resourceType' => 'user', 'resourceId' => '5', 'allowed' => true],
        ];

        $graph->precompute($permissions);

        $graph->invalidate('1');

        $this->assertNull($graph->check('1', 'edit', 'invoice', '100'));
        $this->assertTrue($graph->check('2', 'view', 'user', '5'));
    }

    public function testCacheIntegration(): void
    {
        $cache = new ArrayCache();
        $graph = new PermissionGraph($cache);

        $permissions = [
            ['subjectId' => '1', 'action' => 'edit', 'resourceType' => 'invoice', 'resourceId' => '100', 'allowed' => true],
        ];

        $graph->precompute($permissions);

        // Create a new graph with the same cache
        $newGraph = new PermissionGraph($cache);

        // Should load from cache
        $this->assertTrue($newGraph->check('1', 'edit', 'invoice', '100'));
    }

    public function testInvalidateClearsCache(): void
    {
        $cache = new ArrayCache();
        $graph = new PermissionGraph($cache);

        $permissions = [
            ['subjectId' => '1', 'action' => 'edit', 'resourceType' => 'invoice', 'resourceId' => '100', 'allowed' => true],
        ];

        $graph->precompute($permissions);
        $graph->invalidate();

        // Create a new graph with the same cache
        $newGraph = new PermissionGraph($cache);

        // Should not find anything
        $this->assertNull($newGraph->check('1', 'edit', 'invoice', '100'));
    }

    // ==========================================
    // Role Hierarchy Optimization Tests
    // ==========================================

    public function testSetRoleHierarchy(): void
    {
        $graph = new PermissionGraph();

        $graph->setRoleHierarchy([
            'user:alice' => ['role:manager', 'role:staff'],
            'user:bob' => ['role:developer'],
        ]);

        $this->assertEquals(
            ['user:alice', 'role:manager', 'role:staff'],
            $graph->getEffectiveSubjects('user:alice')
        );

        $this->assertEquals(
            ['user:bob', 'role:developer'],
            $graph->getEffectiveSubjects('user:bob')
        );
    }

    public function testAddRoleInheritanceWithSingleRole(): void
    {
        $graph = new PermissionGraph();

        $graph->addRoleInheritance('user:charlie', 'role:admin');

        $this->assertEquals(
            ['user:charlie', 'role:admin'],
            $graph->getEffectiveSubjects('user:charlie')
        );
    }

    public function testAddRoleInheritanceWithMultipleRoles(): void
    {
        $graph = new PermissionGraph();

        $graph->addRoleInheritance('user:charlie', ['role:admin', 'role:auditor']);

        $effectiveSubjects = $graph->getEffectiveSubjects('user:charlie');

        $this->assertContains('user:charlie', $effectiveSubjects);
        $this->assertContains('role:admin', $effectiveSubjects);
        $this->assertContains('role:auditor', $effectiveSubjects);
        $this->assertCount(3, $effectiveSubjects);
    }

    public function testAddRoleInheritanceIncrementally(): void
    {
        $graph = new PermissionGraph();

        $graph->addRoleInheritance('user:dave', 'role:staff');
        $graph->addRoleInheritance('user:dave', 'role:finance');

        $effectiveSubjects = $graph->getEffectiveSubjects('user:dave');

        $this->assertContains('user:dave', $effectiveSubjects);
        $this->assertContains('role:staff', $effectiveSubjects);
        $this->assertContains('role:finance', $effectiveSubjects);
        $this->assertCount(3, $effectiveSubjects);
    }

    public function testGetEffectiveSubjectsForUnknownSubject(): void
    {
        $graph = new PermissionGraph();

        // Unknown subjects should return array containing only themselves
        $this->assertEquals(
            ['user:unknown'],
            $graph->getEffectiveSubjects('user:unknown')
        );
    }

    public function testCheckMultipleWithoutHierarchy(): void
    {
        $graph = new PermissionGraph();

        $graph->addRule([
            'subject' => 'role:staff',
            'resource' => 'invoice:*',
            'action' => 'view',
            'effect' => 'allow',
        ]);
        $graph->flush();

        // Without hierarchy, check with exact subject
        $result = $graph->checkMultiple(['role:staff'], 'view', 'invoice', '100');
        $this->assertTrue($result);

        // User without the role should not have access
        $result = $graph->checkMultiple(['user:alice'], 'view', 'invoice', '100');
        $this->assertNull($result);
    }

    public function testCheckMultipleWithHierarchy(): void
    {
        $graph = new PermissionGraph();

        // Set up permission for a role
        $graph->addRule([
            'subject' => 'role:staff',
            'resource' => 'invoice:*',
            'action' => 'view',
            'effect' => 'allow',
        ]);
        $graph->flush();

        // Set up hierarchy: user inherits from role
        $graph->setRoleHierarchy([
            'user:alice' => ['role:staff'],
        ]);

        // User should now have access through inherited role
        $result = $graph->checkMultiple(['user:alice'], 'view', 'invoice', '100');
        $this->assertTrue($result);
    }

    public function testRoleHierarchyWithDenyPrecedence(): void
    {
        $graph = new PermissionGraph();

        // Allow via role:staff
        $graph->addRule([
            'subject' => 'role:staff',
            'resource' => 'invoice:*',
            'action' => 'delete',
            'effect' => 'allow',
        ]);

        // Deny via role:intern (more specific denial)
        $graph->addRule([
            'subject' => 'role:intern',
            'resource' => 'invoice:*',
            'action' => 'delete',
            'effect' => 'deny',
        ]);
        $graph->flush();

        // User inherits both roles
        $graph->setRoleHierarchy([
            'user:bob' => ['role:staff', 'role:intern'],
        ]);

        // Deny should take precedence
        $result = $graph->checkMultiple(['user:bob'], 'delete', 'invoice', '100');
        $this->assertFalse($result);
    }

    public function testHierarchyCachePersistence(): void
    {
        $cache = new ArrayCache();
        $graph = new PermissionGraph($cache);

        $graph->setRoleHierarchy([
            'user:alice' => ['role:manager', 'role:staff'],
        ]);

        $graph->addRule([
            'subject' => 'role:manager',
            'resource' => 'report:*',
            'action' => 'view',
            'effect' => 'allow',
        ]);
        $graph->flush();

        // Create new graph instance with same cache
        $newGraph = new PermissionGraph($cache);

        // Hierarchy should be loaded from cache
        $this->assertEquals(
            ['user:alice', 'role:manager', 'role:staff'],
            $newGraph->getEffectiveSubjects('user:alice')
        );

        // Permission check should work with cached hierarchy
        $result = $newGraph->checkMultiple(['user:alice'], 'view', 'report', '1');
        $this->assertTrue($result);
    }

    public function testStatsIncludesRoleHierarchyCount(): void
    {
        $graph = new PermissionGraph();

        $graph->setRoleHierarchy([
            'user:alice' => ['role:manager'],
            'user:bob' => ['role:developer'],
            'user:charlie' => ['role:staff'],
        ]);

        $stats = $graph->getStats();

        $this->assertArrayHasKey('role_hierarchy_count', $stats);
        $this->assertEquals(3, $stats['role_hierarchy_count']);
    }

    public function testComplexRoleHierarchy(): void
    {
        $graph = new PermissionGraph();

        // Set up multi-level hierarchy:
        // role:manager inherits from role:staff
        $graph->setRoleHierarchy([
            'role:manager' => ['role:staff'],
        ]);

        // Then admin inherits from manager (which already has staff)
        $graph->addRoleInheritance('role:admin', 'role:manager');

        // Admin should have all effective subjects in chain
        $adminEffective = $graph->getEffectiveSubjects('role:admin');

        $this->assertContains('role:admin', $adminEffective);
        $this->assertContains('role:manager', $adminEffective);
        $this->assertContains('role:staff', $adminEffective);
    }

    public function testTransitiveRoleInheritance(): void
    {
        $graph = new PermissionGraph();

        // Build hierarchy: staff -> manager -> admin -> superadmin
        $graph->addRoleInheritance('role:manager', 'role:staff');
        $graph->addRoleInheritance('role:admin', 'role:manager');
        $graph->addRoleInheritance('user:superadmin', 'role:admin');

        // Permission at staff level
        $graph->addRule([
            'subject' => 'role:staff',
            'resource' => 'document:*',
            'action' => 'read',
            'effect' => 'allow',
        ]);
        $graph->flush();

        // Superadmin should inherit through the chain
        $result = $graph->checkMultiple(['user:superadmin'], 'read', 'document', '1');
        $this->assertTrue($result);
    }

    public function testMultipleSubjectsWithHierarchy(): void
    {
        $graph = new PermissionGraph();

        // Set up permissions
        $graph->addRule([
            'subject' => 'role:finance',
            'resource' => 'invoice:*',
            'action' => 'approve',
            'effect' => 'allow',
        ]);
        $graph->flush();

        // Multiple users with different role hierarchies
        $graph->setRoleHierarchy([
            'user:alice' => ['role:manager'],
            'user:bob' => ['role:finance'],
        ]);

        // Check multiple subjects at once - bob has finance role
        $result = $graph->checkMultiple(['user:alice', 'user:bob'], 'approve', 'invoice', '100');
        $this->assertTrue($result);
    }

    public function testInvalidateWithHierarchy(): void
    {
        $cache = new ArrayCache();
        $graph = new PermissionGraph($cache);

        $graph->setRoleHierarchy([
            'user:alice' => ['role:manager'],
            'user:bob' => ['role:developer'],
        ]);

        $graph->addRule([
            'subject' => 'role:manager',
            'resource' => 'report:*',
            'action' => 'view',
            'effect' => 'allow',
        ]);
        $graph->flush();

        // Invalidate specific subject
        $graph->invalidate('user:alice');

        // Alice's hierarchy should be removed
        $this->assertEquals(['user:alice'], $graph->getEffectiveSubjects('user:alice'));

        // Bob's hierarchy should remain
        $this->assertEquals(['user:bob', 'role:developer'], $graph->getEffectiveSubjects('user:bob'));
    }

    public function testInvalidateAllClearsHierarchy(): void
    {
        $cache = new ArrayCache();
        $graph = new PermissionGraph($cache);

        $graph->setRoleHierarchy([
            'user:alice' => ['role:manager'],
            'user:bob' => ['role:developer'],
        ]);

        // Full invalidation
        $graph->invalidate();

        // All hierarchies should be cleared
        $this->assertEquals(['user:alice'], $graph->getEffectiveSubjects('user:alice'));
        $this->assertEquals(['user:bob'], $graph->getEffectiveSubjects('user:bob'));

        // New graph should not load hierarchy from cache
        $newGraph = new PermissionGraph($cache);
        $this->assertEquals(['user:alice'], $newGraph->getEffectiveSubjects('user:alice'));
    }

    public function testDuplicateRoleInheritanceIsIgnored(): void
    {
        $graph = new PermissionGraph();

        $graph->addRoleInheritance('user:alice', 'role:staff');
        $graph->addRoleInheritance('user:alice', 'role:staff'); // Duplicate
        $graph->addRoleInheritance('user:alice', ['role:staff', 'role:manager']); // Partial duplicate

        $effectiveSubjects = $graph->getEffectiveSubjects('user:alice');

        // Should only have unique entries
        $this->assertCount(3, $effectiveSubjects);
        $this->assertContains('user:alice', $effectiveSubjects);
        $this->assertContains('role:staff', $effectiveSubjects);
        $this->assertContains('role:manager', $effectiveSubjects);
    }

    public function testEmptyRoleInheritance(): void
    {
        $graph = new PermissionGraph();

        $graph->addRoleInheritance('user:alice', []);

        $effectiveSubjects = $graph->getEffectiveSubjects('user:alice');

        $this->assertEquals(['user:alice'], $effectiveSubjects);
    }

    public function testHierarchyDoesNotAffectDirectSubjectMatch(): void
    {
        $graph = new PermissionGraph();

        // Permission directly on user
        $graph->addRule([
            'subject' => 'user:alice',
            'resource' => 'secret:*',
            'action' => 'view',
            'effect' => 'allow',
        ]);
        $graph->flush();

        // Set hierarchy (but permission is on user, not role)
        $graph->setRoleHierarchy([
            'user:alice' => ['role:staff'],
        ]);

        // Direct user permission should still work
        $result = $graph->checkMultiple(['user:alice'], 'view', 'secret', '1');
        $this->assertTrue($result);
    }

    public function testCheckMultipleReturnsNullWhenNoMatch(): void
    {
        $graph = new PermissionGraph();

        $graph->setRoleHierarchy([
            'user:alice' => ['role:staff'],
        ]);

        // No permissions set, should return null
        $result = $graph->checkMultiple(['user:alice'], 'delete', 'invoice', '100');
        $this->assertNull($result);
    }

    public function testFlushPersistsHierarchyChanges(): void
    {
        $cache = new ArrayCache();
        $graph = new PermissionGraph($cache);

        $graph->addRoleInheritance('user:test', 'role:tester');

        // Manually flush
        $graph->flush();

        // New instance should have the hierarchy
        $newGraph = new PermissionGraph($cache);
        $this->assertEquals(
            ['user:test', 'role:tester'],
            $newGraph->getEffectiveSubjects('user:test')
        );
    }

    public function testSetRoleHierarchyThrowsOnCycle(): void
    {
        $graph = new PermissionGraph();

        $this->expectException(RoleHierarchyCycleException::class);

        $graph->setRoleHierarchy([
            'role:admin' => ['role:manager'],
            'role:manager' => ['role:admin'],
        ]);
    }

    public function testAddRoleInheritanceThrowsOnCycle(): void
    {
        $graph = new PermissionGraph();

        $graph->addRoleInheritance('role:admin', 'role:manager');

        $this->expectException(RoleHierarchyCycleException::class);

        // Introduce cycle
        $graph->addRoleInheritance('role:manager', 'role:admin');
    }

    public function testSetRoleHierarchyDoesNotPersistOnCycle(): void
    {
        $cache = new ArrayCache();
        $graph = new PermissionGraph($cache);

        try {
            $graph->setRoleHierarchy([
                'role:admin' => ['role:manager'],
                'role:manager' => ['role:admin'],
            ]);
            $this->fail('Expected RoleHierarchyCycleException was not thrown');
        } catch (RoleHierarchyCycleException $e) {
            // expected
        }

        // New instance should not see any persisted hierarchy
        $newGraph = new PermissionGraph($cache);
        $this->assertEquals(['role:admin'], $newGraph->getEffectiveSubjects('role:admin'));
        $this->assertEquals(['role:manager'], $newGraph->getEffectiveSubjects('role:manager'));

        $stats = $newGraph->getStats();
        $this->assertEquals(0, $stats['role_hierarchy_count']);
    }

    // ==========================================
    // Production-Grade Tests
    // ==========================================

    // --- Cycle Detection Edge Cases ---

    public function testSelfReferencingCycleThrows(): void
    {
        $graph = new PermissionGraph();

        $this->expectException(RoleHierarchyCycleException::class);

        $graph->setRoleHierarchy([
            'role:admin' => ['role:admin'],
        ]);
    }

    public function testThreeNodeCycleThrows(): void
    {
        $graph = new PermissionGraph();

        $this->expectException(RoleHierarchyCycleException::class);

        $graph->setRoleHierarchy([
            'role:a' => ['role:b'],
            'role:b' => ['role:c'],
            'role:c' => ['role:a'],
        ]);
    }

    public function testCycleExceptionContainsCycleChain(): void
    {
        $graph = new PermissionGraph();

        try {
            $graph->setRoleHierarchy([
                'role:admin' => ['role:manager'],
                'role:manager' => ['role:admin'],
            ]);
            $this->fail('Expected RoleHierarchyCycleException');
        } catch (RoleHierarchyCycleException $e) {
            $cycle = $e->getCycle();
            $this->assertNotEmpty($cycle);
            $this->assertContains('role:admin', $cycle);
            $this->assertContains('role:manager', $cycle);
            $this->assertStringContainsString('role:admin', $e->getMessage());
        }
    }

    public function testAddRoleInheritanceDoesNotPersistOnCycle(): void
    {
        $cache = new ArrayCache();
        $graph = new PermissionGraph($cache);

        $graph->addRoleInheritance('role:admin', 'role:manager');
        $graph->flush();

        try {
            $graph->addRoleInheritance('role:manager', 'role:admin');
            $this->fail('Expected RoleHierarchyCycleException');
        } catch (RoleHierarchyCycleException $e) {
            // Expected
        }

        $newGraph = new PermissionGraph($cache);
        $managerEffective = $newGraph->getEffectiveSubjects('role:manager');
        $this->assertEquals(['role:manager'], $managerEffective);
    }

    public function testPartialCycleInLargeHierarchy(): void
    {
        $graph = new PermissionGraph();

        $this->expectException(RoleHierarchyCycleException::class);

        $graph->setRoleHierarchy([
            'user:alice' => ['role:admin'],
            'role:admin' => ['role:manager'],
            'role:manager' => ['role:staff', 'role:finance'],
            'role:staff' => ['role:basic'],
            'role:finance' => ['role:manager'],
            'role:basic' => [],
        ]);
    }

    // --- Large Scale Tests ---

    public function testLargeHierarchyPerformance(): void
    {
        $graph = new PermissionGraph();

        $roles = [];
        for ($i = 1; $i <= 100; $i++) {
            $roles[] = "role:role_{$i}";
        }

        $graph->setRoleHierarchy([
            'user:superuser' => $roles,
        ]);

        $effectiveSubjects = $graph->getEffectiveSubjects('user:superuser');
        $this->assertCount(101, $effectiveSubjects);
        $this->assertContains('user:superuser', $effectiveSubjects);
        $this->assertContains('role:role_100', $effectiveSubjects);
    }

    public function testDeepHierarchyChain(): void
    {
        $graph = new PermissionGraph();

        $hierarchy = [];
        for ($i = 1; $i < 20; $i++) {
            $hierarchy["role:level_{$i}"] = ["role:level_" . ($i + 1)];
        }
        $hierarchy['role:level_20'] = [];

        $graph->setRoleHierarchy($hierarchy);
        $graph->addRoleInheritance('user:deep', 'role:level_1');

        $effectiveSubjects = $graph->getEffectiveSubjects('user:deep');
        $this->assertCount(21, $effectiveSubjects);
        $this->assertContains('role:level_20', $effectiveSubjects);
    }

    // --- Edge Cases ---

    public function testEmptySubjectArray(): void
    {
        $graph = new PermissionGraph();

        $graph->addRule([
            'subject' => 'role:staff',
            'resource' => 'invoice:*',
            'action' => 'view',
            'effect' => 'allow',
        ]);
        $graph->flush();

        $result = $graph->checkMultiple([], 'view', 'invoice', '100');
        $this->assertNull($result);
    }

    public function testSpecialCharactersInSubjectNames(): void
    {
        $graph = new PermissionGraph();

        $graph->setRoleHierarchy([
            'user:alice@example.com' => ['role:user-admin', 'role:super_user'],
        ]);

        $effectiveSubjects = $graph->getEffectiveSubjects('user:alice@example.com');
        $this->assertContains('user:alice@example.com', $effectiveSubjects);
        $this->assertContains('role:user-admin', $effectiveSubjects);
    }

    public function testNumericSubjectIds(): void
    {
        $graph = new PermissionGraph();

        $graph->setRoleHierarchy([
            'user:12345' => ['role:100', 'role:200'],
        ]);

        $graph->addRule([
            'subject' => 'role:100',
            'resource' => 'item:*',
            'action' => 'view',
            'effect' => 'allow',
        ]);
        $graph->flush();

        $result = $graph->checkMultiple(['user:12345'], 'view', 'item', '999');
        $this->assertTrue($result);
    }

    // --- State Management Tests ---

    public function testSetRoleHierarchyReplacesExisting(): void
    {
        $graph = new PermissionGraph();

        $graph->setRoleHierarchy([
            'user:alice' => ['role:admin', 'role:manager'],
        ]);

        $graph->setRoleHierarchy([
            'user:alice' => ['role:staff'],
        ]);

        $effectiveSubjects = $graph->getEffectiveSubjects('user:alice');
        $this->assertCount(2, $effectiveSubjects);
        $this->assertNotContains('role:admin', $effectiveSubjects);
    }

    public function testClearMethodClearsHierarchy(): void
    {
        $graph = new PermissionGraph();

        $graph->setRoleHierarchy([
            'user:alice' => ['role:admin'],
        ]);

        $graph->clear();

        $this->assertEquals(['user:alice'], $graph->getEffectiveSubjects('user:alice'));
    }

    public function testMultipleDenyRulesAcrossHierarchy(): void
    {
        $graph = new PermissionGraph();

        $graph->addRule([
            'subject' => 'role:staff',
            'resource' => 'document:*',
            'action' => 'edit',
            'effect' => 'allow',
        ]);

        $graph->addRule([
            'subject' => 'role:contractor',
            'resource' => 'document:*',
            'action' => 'edit',
            'effect' => 'deny',
        ]);
        $graph->flush();

        $graph->setRoleHierarchy([
            'user:bob' => ['role:staff', 'role:contractor'],
        ]);

        $result = $graph->checkMultiple(['user:bob'], 'edit', 'document', '1');
        $this->assertFalse($result);
    }

    // --- Stats Tests ---

    public function testStatsAfterInvalidation(): void
    {
        $graph = new PermissionGraph();

        $graph->setRoleHierarchy([
            'user:alice' => ['role:admin'],
            'user:bob' => ['role:staff'],
        ]);

        $graph->invalidate('user:alice');

        $stats = $graph->getStats();
        $this->assertEquals(1, $stats['role_hierarchy_count']);
    }

    public function testStatsWithEmptyGraph(): void
    {
        $graph = new PermissionGraph();

        $stats = $graph->getStats();

        $this->assertEquals(0, $stats['total_rules']);
        $this->assertEquals(0, $stats['role_hierarchy_count']);
        $this->assertEmpty($stats['by_resource_type']);
    }

    // --- Diamond Inheritance Pattern ---

    public function testDiamondInheritancePattern(): void
    {
        $graph = new PermissionGraph();

        $graph->setRoleHierarchy([
            'role:staff' => [],
            'role:manager' => ['role:staff'],
            'role:auditor' => ['role:staff'],
            'role:admin' => ['role:manager', 'role:auditor'],
        ]);

        $adminEffective = $graph->getEffectiveSubjects('role:admin');

        $this->assertContains('role:admin', $adminEffective);
        $this->assertContains('role:manager', $adminEffective);
        $this->assertContains('role:auditor', $adminEffective);
        $this->assertContains('role:staff', $adminEffective);
        $this->assertCount(4, $adminEffective);
    }

    public function testDiamondInheritanceWithPermissions(): void
    {
        $graph = new PermissionGraph();

        $graph->addRule([
            'subject' => 'role:staff',
            'resource' => 'document:*',
            'action' => 'read',
            'effect' => 'allow',
        ]);
        $graph->flush();

        $graph->setRoleHierarchy([
            'role:staff' => [],
            'role:manager' => ['role:staff'],
            'role:auditor' => ['role:staff'],
            'role:admin' => ['role:manager', 'role:auditor'],
        ]);

        $graph->addRoleInheritance('user:superadmin', 'role:admin');

        $result = $graph->checkMultiple(['user:superadmin'], 'read', 'document', '1');
        $this->assertTrue($result);
    }

    // --- Input Sanitization ---

    public function testEmptyStringInInheritedSubjects(): void
    {
        $graph = new PermissionGraph();

        $graph->addRoleInheritance('user:alice', ['role:staff', '', 'role:manager', '']);

        $effectiveSubjects = $graph->getEffectiveSubjects('user:alice');

        $this->assertCount(3, $effectiveSubjects);
        $this->assertNotContains('', $effectiveSubjects);
    }

    // --- Sequential Operations ---

    public function testSequentialHierarchyUpdates(): void
    {
        $cache = new ArrayCache();
        $graph = new PermissionGraph($cache);

        $graph->addRoleInheritance('user:alice', 'role:staff');
        $graph->flush();

        $graph->addRoleInheritance('user:alice', 'role:manager');
        $graph->flush();

        $newGraph = new PermissionGraph($cache);
        $effectiveSubjects = $newGraph->getEffectiveSubjects('user:alice');

        $this->assertCount(3, $effectiveSubjects);
        $this->assertContains('role:staff', $effectiveSubjects);
        $this->assertContains('role:manager', $effectiveSubjects);
    }

    public function testHierarchyRebuildFromScratch(): void
    {
        $cache = new ArrayCache();
        
        $graph1 = new PermissionGraph($cache);
        $graph1->setRoleHierarchy([
            'user:alice' => ['role:admin'],
        ]);

        $graph1->invalidate();

        $graph1->setRoleHierarchy([
            'user:alice' => ['role:manager'],
            'user:bob' => ['role:staff'],
        ]);

        $graph2 = new PermissionGraph($cache);
        
        $this->assertEquals(['user:alice', 'role:manager'], $graph2->getEffectiveSubjects('user:alice'));
        $this->assertEquals(['user:bob', 'role:staff'], $graph2->getEffectiveSubjects('user:bob'));
    }

    // --- Valid Hierarchy Tests (No False Positives in Cycle Detection) ---

    public function testComplexGraphWithoutCycle(): void
    {
        $graph = new PermissionGraph();

        // Complex but valid hierarchy:
        //
        //       superadmin
        //       /    |    \
        //    admin  cto   cfo
        //      |     |     |
        //   manager devlead finlead
        //      \     |     /
        //        \   |   /
        //          staff

        $graph->setRoleHierarchy([
            'role:superadmin' => ['role:admin', 'role:cto', 'role:cfo'],
            'role:admin' => ['role:manager'],
            'role:cto' => ['role:devlead'],
            'role:cfo' => ['role:finlead'],
            'role:manager' => ['role:staff'],
            'role:devlead' => ['role:staff'],
            'role:finlead' => ['role:staff'],
            'role:staff' => [],
        ]);

        $superadminEffective = $graph->getEffectiveSubjects('role:superadmin');

        $this->assertContains('role:superadmin', $superadminEffective);
        $this->assertContains('role:admin', $superadminEffective);
        $this->assertContains('role:cto', $superadminEffective);
        $this->assertContains('role:cfo', $superadminEffective);
        $this->assertContains('role:manager', $superadminEffective);
        $this->assertContains('role:devlead', $superadminEffective);
        $this->assertContains('role:finlead', $superadminEffective);
        $this->assertContains('role:staff', $superadminEffective);

        // Staff should appear only once
        $staffCount = array_count_values($superadminEffective)['role:staff'] ?? 0;
        $this->assertEquals(1, $staffCount);
    }

    public function testNoCycleInLargeValidHierarchy(): void
    {
        $graph = new PermissionGraph();

        // Large valid tree: 5 levels, branching factor 3
        // Total nodes: 1 + 3 + 9 + 27 = 40
        $hierarchy = [];
        $hierarchy['role:root'] = ['role:a1', 'role:a2', 'role:a3'];

        $hierarchy['role:a1'] = ['role:b1', 'role:b2', 'role:b3'];
        $hierarchy['role:a2'] = ['role:b4', 'role:b5', 'role:b6'];
        $hierarchy['role:a3'] = ['role:b7', 'role:b8', 'role:b9'];

        for ($i = 1; $i <= 9; $i++) {
            $hierarchy["role:b{$i}"] = [
                "role:c" . (($i - 1) * 3 + 1),
                "role:c" . (($i - 1) * 3 + 2),
                "role:c" . (($i - 1) * 3 + 3),
            ];
        }

        for ($i = 1; $i <= 27; $i++) {
            $hierarchy["role:c{$i}"] = [];
        }

        // Should NOT throw
        $graph->setRoleHierarchy($hierarchy);

        $rootEffective = $graph->getEffectiveSubjects('role:root');

        $this->assertContains('role:root', $rootEffective);
        $this->assertContains('role:a1', $rootEffective);
        $this->assertContains('role:b5', $rootEffective);
        $this->assertContains('role:c27', $rootEffective);
        $this->assertCount(40, $rootEffective);
    }

    public function testValidDAGWithMultiplePathsToSameNode(): void
    {
        $graph = new PermissionGraph();

        // Valid DAG with convergent paths (NOT a cycle)
        //     A
        //    / \
        //   B   C
        //    \ /
        //     D
        //     |
        //     E

        $graph->setRoleHierarchy([
            'role:A' => ['role:B', 'role:C'],
            'role:B' => ['role:D'],
            'role:C' => ['role:D'],
            'role:D' => ['role:E'],
            'role:E' => [],
        ]);

        $effectiveA = $graph->getEffectiveSubjects('role:A');

        $this->assertCount(5, $effectiveA);
        $this->assertContains('role:A', $effectiveA);
        $this->assertContains('role:B', $effectiveA);
        $this->assertContains('role:C', $effectiveA);
        $this->assertContains('role:D', $effectiveA);
        $this->assertContains('role:E', $effectiveA);
    }

    public function testIncrementalBuildOfValidHierarchy(): void
    {
        $graph = new PermissionGraph();

        // Build valid hierarchy incrementally
        $graph->addRoleInheritance('role:admin', 'role:manager');
        $graph->addRoleInheritance('role:manager', 'role:staff');
        $graph->addRoleInheritance('role:admin', 'role:auditor');
        $graph->addRoleInheritance('role:auditor', 'role:readonly');
        $graph->addRoleInheritance('user:superuser', 'role:admin');

        $userEffective = $graph->getEffectiveSubjects('user:superuser');

        $this->assertContains('user:superuser', $userEffective);
        $this->assertContains('role:admin', $userEffective);
        $this->assertContains('role:manager', $userEffective);
        $this->assertContains('role:staff', $userEffective);
        $this->assertContains('role:auditor', $userEffective);
        $this->assertContains('role:readonly', $userEffective);
        $this->assertCount(6, $userEffective);
    }
}
