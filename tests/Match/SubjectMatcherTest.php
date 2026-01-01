<?php

declare(strict_types=1);

namespace Authza\Tests\Match;

use Authza\Interfaces\SubjectInterface;
use Authza\Match\SubjectMatcher;
use PHPUnit\Framework\TestCase;

class SubjectMatcherTest extends TestCase
{
    private SubjectInterface $user;
    private SubjectMatcher $matcher;

    protected function setUp(): void
    {
        $this->matcher = new SubjectMatcher();
        
        $this->user = new class implements SubjectInterface {
            public function getId(): string|int
            {
                return '42';
            }

            public function getRoles(): array
            {
                return ['admin', 'accountant'];
            }

            public function getAttributes(): array
            {
                return [];
            }
        };
    }

    public function testMatchesRoleExact(): void
    {
        $this->assertTrue($this->matcher->matches('role:admin', $this->user));
        $this->assertTrue($this->matcher->matches('role:accountant', $this->user));
        $this->assertFalse($this->matcher->matches('role:manager', $this->user));
    }

    public function testMatchesRoleWildcard(): void
    {
        $this->assertTrue($this->matcher->matches('role:*', $this->user));
    }

    public function testMatchesRoleWildcardWithoutRoles(): void
    {
        $userWithoutRoles = new class implements SubjectInterface {
            public function getId(): string|int
            {
                return '99';
            }

            public function getRoles(): array
            {
                return [];
            }

            public function getAttributes(): array
            {
                return [];
            }
        };

        $this->assertFalse($this->matcher->matches('role:*', $userWithoutRoles));
    }

    public function testMatchesUserExact(): void
    {
        $this->assertTrue($this->matcher->matches('user:42', $this->user));
        $this->assertFalse($this->matcher->matches('user:99', $this->user));
    }

    public function testMatchesUserWildcard(): void
    {
        $this->assertTrue($this->matcher->matches('user:*', $this->user));
    }

    public function testMatchesInvalidPattern(): void
    {
        $this->assertFalse($this->matcher->matches('invalid', $this->user));
        $this->assertFalse($this->matcher->matches('', $this->user));
    }

    public function testMatchesUnknownType(): void
    {
        $this->assertFalse($this->matcher->matches('group:developers', $this->user));
    }

    public function testMatchesWithIntegerId(): void
    {
        $userWithIntId = new class implements SubjectInterface {
            public function getId(): string|int
            {
                return 42; // int instead of string
            }

            public function getRoles(): array
            {
                return ['admin'];
            }

            public function getAttributes(): array
            {
                return [];
            }
        };

        $this->assertTrue($this->matcher->matches('user:42', $userWithIntId));
    }
}
