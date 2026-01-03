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
}
