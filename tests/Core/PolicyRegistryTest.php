<?php

declare(strict_types=1);

namespace Authza\Tests\Core;

use Authza\Core\PolicyRegistry;
use Authza\Policies\UserPolicy;
use Authza\Policies\InvoicePolicy;
use PHPUnit\Framework\TestCase;

class PolicyRegistryTest extends TestCase
{
    public function testRegisterAndGetPolicy(): void
    {
        $registry = new PolicyRegistry();
        $userPolicy = new UserPolicy();

        $registry->register('user', $userPolicy);

        $this->assertSame($userPolicy, $registry->get('user'));
    }

    public function testGetNonExistentPolicy(): void
    {
        $registry = new PolicyRegistry();

        $this->assertNull($registry->get('nonexistent'));
    }

    public function testConstructorWithPolicies(): void
    {
        $userPolicy = new UserPolicy();
        $invoicePolicy = new InvoicePolicy();

        $registry = new PolicyRegistry([
            'user' => $userPolicy,
            'invoice' => $invoicePolicy,
        ]);

        $this->assertSame($userPolicy, $registry->get('user'));
        $this->assertSame($invoicePolicy, $registry->get('invoice'));
    }

    public function testAutoDiscovery(): void
    {
        $registry = new PolicyRegistry();
        $policyDir = __DIR__ . '/../../src/Policies';

        $registry->autoDiscover('Authza\\Policies', $policyDir);

        $this->assertInstanceOf(UserPolicy::class, $registry->get('user'));
        $this->assertInstanceOf(InvoicePolicy::class, $registry->get('invoice'));
    }

    public function testAutoDiscoveryWithNonExistentDirectory(): void
    {
        $registry = new PolicyRegistry();
        
        // Should not throw exception
        $registry->autoDiscover('Authza\\Policies', '/nonexistent/directory');

        $this->assertNull($registry->get('user'));
    }

    public function testAutoDiscoveryInTempDirectory(): void
    {
        $tempDir = sys_get_temp_dir() . '/authza_test_policies_' . uniqid();
        mkdir($tempDir);

        // Create a mock policy file
        $policyContent = <<<'PHP'
<?php
namespace Test;
use Authza\Interfaces\PolicyInterface;
use Authza\Interfaces\SubjectInterface;
use Authza\Interfaces\ResourceInterface;

class TestPolicy implements PolicyInterface {
    public function supports(string $resourceType): bool { return $resourceType === 'test'; }
    public function can(SubjectInterface $subject, string $action, ResourceInterface $resource, array $context = []): bool { return true; }
}
PHP;

        file_put_contents($tempDir . '/TestPolicy.php', $policyContent);

        require_once $tempDir . '/TestPolicy.php';

        $registry = new PolicyRegistry();
        $registry->autoDiscover('Test', $tempDir);

        $this->assertInstanceOf(\Test\TestPolicy::class, $registry->get('test'));

        // Cleanup
        unlink($tempDir . '/TestPolicy.php');
        rmdir($tempDir);
    }
}
