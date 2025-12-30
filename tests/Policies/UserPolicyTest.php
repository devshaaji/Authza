<?php

declare(strict_types=1);

namespace Authza\Tests\Policies;

use Authza\Policies\UserPolicy;
use Authza\Tests\Mocks\MockResource;
use Authza\Tests\Mocks\MockSubject;
use PHPUnit\Framework\TestCase;

class UserPolicyTest extends TestCase
{
    private UserPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new UserPolicy();
    }

    public function testSupportsUserResourceType(): void
    {
        $this->assertTrue($this->policy->supports('user'));
        $this->assertFalse($this->policy->supports('invoice'));
    }

    public function testAdminCanViewAnyUser(): void
    {
        $admin = new MockSubject('1', ['admin']);
        $resource = new MockResource('user', '2');

        $this->assertTrue($this->policy->can($admin, 'view', $resource));
    }

    public function testUserCanViewThemselves(): void
    {
        $user = new MockSubject('1', ['user']);
        $resource = new MockResource('user', '1');

        $this->assertTrue($this->policy->can($user, 'view', $resource));
    }

    public function testUserCannotViewOtherUsers(): void
    {
        $user = new MockSubject('1', ['user']);
        $resource = new MockResource('user', '2');

        $this->assertFalse($this->policy->can($user, 'view', $resource));
    }

    public function testAdminCanEditAnyUser(): void
    {
        $admin = new MockSubject('1', ['admin']);
        $resource = new MockResource('user', '2');

        $this->assertTrue($this->policy->can($admin, 'edit', $resource));
    }

    public function testUserCanEditThemselves(): void
    {
        $user = new MockSubject('1', ['user']);
        $resource = new MockResource('user', '1');

        $this->assertTrue($this->policy->can($user, 'edit', $resource));
    }

    public function testUserCannotEditOtherUsers(): void
    {
        $user = new MockSubject('1', ['user']);
        $resource = new MockResource('user', '2');

        $this->assertFalse($this->policy->can($user, 'edit', $resource));
    }

    public function testOnlyAdminCanDelete(): void
    {
        $admin = new MockSubject('1', ['admin']);
        $user = new MockSubject('2', ['user']);
        $resource = new MockResource('user', '3');

        $this->assertTrue($this->policy->can($admin, 'delete', $resource));
        $this->assertFalse($this->policy->can($user, 'delete', $resource));
    }

    public function testUserCannotDeleteThemselves(): void
    {
        $user = new MockSubject('1', ['user']);
        $resource = new MockResource('user', '1');

        $this->assertFalse($this->policy->can($user, 'delete', $resource));
    }

    public function testUnknownActionDenied(): void
    {
        $admin = new MockSubject('1', ['admin']);
        $resource = new MockResource('user', '2');

        $this->assertFalse($this->policy->can($admin, 'unknown', $resource));
    }
}
