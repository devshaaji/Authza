<?php

declare(strict_types=1);

namespace Authza\Tests\Core\Graph;

use Authza\Adapters\Cache\ArrayCache;
use Authza\Core\Graph\PermissionGraph;
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
}
