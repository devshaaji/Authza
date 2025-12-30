<?php

declare(strict_types=1);

namespace Authza\Tests\Core;

use Authza\Adapters\Cache\ArrayCache;
use Authza\Core\Authorization;
use Authza\Core\Graph\PermissionGraph;
use Authza\Core\PolicyRegistry;
use Authza\Exceptions\AuthorizationException;
use Authza\Policies\UserPolicy;
use Authza\Policies\InvoicePolicy;
use Authza\Tests\Mocks\MockLogger;
use Authza\Tests\Mocks\MockResource;
use Authza\Tests\Mocks\MockSubject;
use PHPUnit\Framework\TestCase;

class AuthorizationTest extends TestCase
{
    public function testCanReturnsTrue(): void
    {
        $registry = new PolicyRegistry(['user' => new UserPolicy()]);
        $authz = new Authorization($registry);

        $admin = new MockSubject('1', ['admin']);
        $resource = new MockResource('user', '2');

        $this->assertTrue($authz->can($admin, 'view', $resource));
    }

    public function testCanReturnsFalse(): void
    {
        $registry = new PolicyRegistry(['user' => new UserPolicy()]);
        $authz = new Authorization($registry);

        $user = new MockSubject('1', ['user']);
        $resource = new MockResource('user', '2');

        $this->assertFalse($authz->can($user, 'view', $resource));
    }

    public function testAuthorizeDoesNotThrowWhenAllowed(): void
    {
        $registry = new PolicyRegistry(['user' => new UserPolicy()]);
        $authz = new Authorization($registry);

        $admin = new MockSubject('1', ['admin']);
        $resource = new MockResource('user', '2');

        $authz->authorize($admin, 'view', $resource);
        $this->assertTrue(true); // If we get here, no exception was thrown
    }

    public function testAuthorizeThrowsWhenDenied(): void
    {
        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('Access denied: 1 cannot view user:2');

        $registry = new PolicyRegistry(['user' => new UserPolicy()]);
        $authz = new Authorization($registry);

        $user = new MockSubject('1', ['user']);
        $resource = new MockResource('user', '2');

        $authz->authorize($user, 'view', $resource);
    }

    public function testCacheIntegration(): void
    {
        $cache = new ArrayCache();
        $registry = new PolicyRegistry(['user' => new UserPolicy()]);
        $authz = new Authorization($registry, $cache);

        $admin = new MockSubject('1', ['admin']);
        $resource = new MockResource('user', '2');

        // First call - should evaluate policy
        $result1 = $authz->can($admin, 'view', $resource);

        // Second call - should hit cache
        $result2 = $authz->can($admin, 'view', $resource);

        $this->assertTrue($result1);
        $this->assertTrue($result2);

        // Verify cache was used
        $cacheKey = 'authz:1:view:user:2';
        $this->assertTrue($cache->has($cacheKey));
    }

    public function testPermissionGraphIntegration(): void
    {
        $cache = new ArrayCache();
        $graph = new PermissionGraph($cache);

        $permissions = [
            ['subjectId' => '1', 'action' => 'view', 'resourceType' => 'user', 'resourceId' => '2', 'allowed' => true],
        ];
        $graph->precompute($permissions);

        $registry = new PolicyRegistry(['user' => new UserPolicy()]);
        $authz = new Authorization($registry, $cache, null, $graph);

        $user = new MockSubject('1', ['user']);
        $resource = new MockResource('user', '2');

        // Should use graph instead of policy
        $this->assertTrue($authz->can($user, 'view', $resource));
    }

    public function testLoggerIntegrationAllowed(): void
    {
        $logger = new MockLogger();
        $registry = new PolicyRegistry(['user' => new UserPolicy()]);
        $authz = new Authorization($registry, null, $logger);

        $admin = new MockSubject('1', ['admin']);
        $resource = new MockResource('user', '2');

        $authz->can($admin, 'view', $resource);

        $this->assertCount(1, $logger->logs);
        $this->assertEquals('info', $logger->logs[0]['level']);
        $this->assertStringContainsString('allowed', $logger->logs[0]['message']);
    }

    public function testLoggerIntegrationDenied(): void
    {
        $logger = new MockLogger();
        $registry = new PolicyRegistry(['user' => new UserPolicy()]);
        $authz = new Authorization($registry, null, $logger);

        $user = new MockSubject('1', ['user']);
        $resource = new MockResource('user', '2');

        $authz->can($user, 'view', $resource);

        $this->assertCount(1, $logger->logs);
        $this->assertEquals('warning', $logger->logs[0]['level']);
        $this->assertStringContainsString('denied', $logger->logs[0]['message']);
    }

    public function testNoPolicyReturnsFalse(): void
    {
        $registry = new PolicyRegistry();
        $authz = new Authorization($registry);

        $admin = new MockSubject('1', ['admin']);
        $resource = new MockResource('unknown', '2');

        $this->assertFalse($authz->can($admin, 'view', $resource));
    }

    public function testQuickStart(): void
    {
        $authz = Authorization::quickStart();

        $this->assertInstanceOf(Authorization::class, $authz);
    }

    public function testQuickStartWithCache(): void
    {
        $cache = new ArrayCache();
        $authz = Authorization::quickStart(['cache' => $cache]);

        $this->assertInstanceOf(Authorization::class, $authz);
    }

    public function testQuickStartWithLogger(): void
    {
        $logger = new MockLogger();
        $authz = Authorization::quickStart(['logger' => $logger]);

        $this->assertInstanceOf(Authorization::class, $authz);
    }

    public function testQuickStartWithAutoDiscovery(): void
    {
        $policyDir = __DIR__ . '/../../src/Policies';
        $authz = Authorization::quickStart([
            'policyNamespace' => 'Authza\\Policies',
            'policyDirectory' => $policyDir,
        ]);

        $admin = new MockSubject('1', ['admin']);
        $resource = new MockResource('user', '2');

        $this->assertTrue($authz->can($admin, 'view', $resource));
    }

    public function testContextPassing(): void
    {
        $registry = new PolicyRegistry(['invoice' => new InvoicePolicy()]);
        $authz = new Authorization($registry);

        $admin = new MockSubject('1', ['admin']);
        $resource = new MockResource('invoice', '100', '2');

        // Can edit with pending status
        $this->assertTrue($authz->can($admin, 'edit', $resource, ['status' => 'pending']));

        // Cannot edit with paid status
        $this->assertFalse($authz->can($admin, 'edit', $resource, ['status' => 'paid']));
    }
}
