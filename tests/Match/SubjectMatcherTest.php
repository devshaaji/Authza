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

    public function testMatchesUnknownTypeDoesNotMatch(): void
    {
        $this->assertFalse($this->matcher->matches('group:developers', $this->user));
    }

    public function testMatchesWithIntegerId(): void
    {
        $userWithIntId = new class implements SubjectInterface {
            public function getId(): string|int
            {
                return 42;
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

    public function testMatchesGlobalWildcard(): void
    {
        $this->assertTrue($this->matcher->matches('*', $this->user));
        
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
        
        $this->assertTrue($this->matcher->matches('*', $userWithoutRoles));
    }

    public function testMatchesValueWildcard(): void
    {
        $this->assertTrue($this->matcher->matches('*:admin', $this->user));
        $this->assertTrue($this->matcher->matches('*:accountant', $this->user));
        $this->assertTrue($this->matcher->matches('*:42', $this->user));
        $this->assertFalse($this->matcher->matches('*:manager', $this->user));
    }

    public function testBuildUserSubjects(): void
    {
        $subjects = $this->matcher->buildUserSubjects($this->user);
        
        $this->assertContains('user:42', $subjects);
        $this->assertContains('role:admin', $subjects);
        $this->assertContains('role:accountant', $subjects);
        $this->assertCount(3, $subjects);
    }

    public function testMatchesSubjectStringExact(): void
    {
        $this->assertTrue($this->matcher->matchesSubjectString('user:42', 'user:42'));
        $this->assertTrue($this->matcher->matchesSubjectString('role:admin', 'role:admin'));
        $this->assertFalse($this->matcher->matchesSubjectString('user:42', 'user:99'));
    }

    public function testMatchesSubjectStringWildcard(): void
    {
        $this->assertTrue($this->matcher->matchesSubjectString('user:*', 'user:42'));
        $this->assertTrue($this->matcher->matchesSubjectString('user:*', 'user:99'));
        $this->assertTrue($this->matcher->matchesSubjectString('*:admin', 'role:admin'));
        $this->assertTrue($this->matcher->matchesSubjectString('*', 'user:42'));
        $this->assertTrue($this->matcher->matchesSubjectString('*:*', 'user:42'));
    }

    public function testMatchesSubjectStringSegmentMismatch(): void
    {
        $this->assertFalse($this->matcher->matchesSubjectString('user:*', 'tenant:acme:user:42'));
        $this->assertFalse($this->matcher->matchesSubjectString('*:*', 'a:b:c'));
    }

    public function testMatchesSubjectStringMultiSegment(): void
    {
        $this->assertTrue($this->matcher->matchesSubjectString('tenant:acme:user:42', 'tenant:acme:user:42'));
        $this->assertTrue($this->matcher->matchesSubjectString('tenant:*:user:42', 'tenant:acme:user:42'));
        $this->assertTrue($this->matcher->matchesSubjectString('tenant:acme:*:*', 'tenant:acme:user:42'));
        $this->assertFalse($this->matcher->matchesSubjectString('tenant:*:user:42', 'tenant:acme:user:99'));
    }
}
