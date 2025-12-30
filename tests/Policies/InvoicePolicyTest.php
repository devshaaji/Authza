<?php

declare(strict_types=1);

namespace Authza\Tests\Policies;

use Authza\Policies\InvoicePolicy;
use Authza\Tests\Mocks\MockResource;
use Authza\Tests\Mocks\MockSubject;
use PHPUnit\Framework\TestCase;

class InvoicePolicyTest extends TestCase
{
    private InvoicePolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new InvoicePolicy();
    }

    public function testSupportsInvoiceResourceType(): void
    {
        $this->assertTrue($this->policy->supports('invoice'));
        $this->assertFalse($this->policy->supports('user'));
    }

    public function testAdminCanViewInvoice(): void
    {
        $admin = new MockSubject('1', ['admin']);
        $resource = new MockResource('invoice', '100', '2');

        $this->assertTrue($this->policy->can($admin, 'view', $resource));
    }

    public function testAccountantCanViewInvoice(): void
    {
        $accountant = new MockSubject('1', ['accountant']);
        $resource = new MockResource('invoice', '100', '2');

        $this->assertTrue($this->policy->can($accountant, 'view', $resource));
    }

    public function testOwnerCanViewInvoice(): void
    {
        $owner = new MockSubject('1', ['user']);
        $resource = new MockResource('invoice', '100', '1');

        $this->assertTrue($this->policy->can($owner, 'view', $resource));
    }

    public function testNonOwnerCannotViewInvoice(): void
    {
        $user = new MockSubject('1', ['user']);
        $resource = new MockResource('invoice', '100', '2');

        $this->assertFalse($this->policy->can($user, 'view', $resource));
    }

    public function testAdminCanEditUnpaidInvoice(): void
    {
        $admin = new MockSubject('1', ['admin']);
        $resource = new MockResource('invoice', '100', '2');

        $this->assertTrue($this->policy->can($admin, 'edit', $resource, ['status' => 'pending']));
    }

    public function testAdminCannotEditPaidInvoice(): void
    {
        $admin = new MockSubject('1', ['admin']);
        $resource = new MockResource('invoice', '100', '2');

        $this->assertFalse($this->policy->can($admin, 'edit', $resource, ['status' => 'paid']));
    }

    public function testAccountantCanEditUnpaidInvoice(): void
    {
        $accountant = new MockSubject('1', ['accountant']);
        $resource = new MockResource('invoice', '100', '2');

        $this->assertTrue($this->policy->can($accountant, 'edit', $resource, ['status' => 'pending']));
    }

    public function testUserCannotEditInvoice(): void
    {
        $user = new MockSubject('1', ['user']);
        $resource = new MockResource('invoice', '100', '1');

        $this->assertFalse($this->policy->can($user, 'edit', $resource, ['status' => 'pending']));
    }

    public function testOnlyAdminCanDelete(): void
    {
        $admin = new MockSubject('1', ['admin']);
        $accountant = new MockSubject('2', ['accountant']);
        $resource = new MockResource('invoice', '100', '3');

        $this->assertTrue($this->policy->can($admin, 'delete', $resource));
        $this->assertFalse($this->policy->can($accountant, 'delete', $resource));
    }

    public function testAdminCanApprove(): void
    {
        $admin = new MockSubject('1', ['admin']);
        $resource = new MockResource('invoice', '100', '2');

        $this->assertTrue($this->policy->can($admin, 'approve', $resource));
    }

    public function testManagerCanApprove(): void
    {
        $manager = new MockSubject('1', ['manager']);
        $resource = new MockResource('invoice', '100', '2');

        $this->assertTrue($this->policy->can($manager, 'approve', $resource));
    }

    public function testAccountantCannotApprove(): void
    {
        $accountant = new MockSubject('1', ['accountant']);
        $resource = new MockResource('invoice', '100', '2');

        $this->assertFalse($this->policy->can($accountant, 'approve', $resource));
    }

    public function testUnknownActionDenied(): void
    {
        $admin = new MockSubject('1', ['admin']);
        $resource = new MockResource('invoice', '100', '2');

        $this->assertFalse($this->policy->can($admin, 'unknown', $resource));
    }
}
