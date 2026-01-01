<?php

declare(strict_types=1);

namespace Authza\Tests\Match;

use Authza\Interfaces\ResourceInterface;
use Authza\Match\ResourceMatcher;
use PHPUnit\Framework\TestCase;

class ResourceMatcherTest extends TestCase
{
    private ResourceInterface $resource;
    private ResourceMatcher $matcher;

    protected function setUp(): void
    {
        $this->matcher = new ResourceMatcher();
        
        $this->resource = new class implements ResourceInterface {
            public function getResourceType(): string
            {
                return 'invoice';
            }

            public function getResourceId(): string|int
            {
                return '123';
            }

            public function getOwnerId(): string|int|null
            {
                return '42';
            }

            public function getAttributes(): array
            {
                return [];
            }
        };
    }

    public function testMatchesTypeOnly(): void
    {
        $this->assertTrue($this->matcher->matches('invoice', $this->resource));
        $this->assertFalse($this->matcher->matches('user', $this->resource));
    }

    public function testMatchesTypeAndIdExact(): void
    {
        $this->assertTrue($this->matcher->matches('invoice:123', $this->resource));
        $this->assertFalse($this->matcher->matches('invoice:456', $this->resource));
    }

    public function testMatchesTypeWithWildcard(): void
    {
        $this->assertTrue($this->matcher->matches('invoice:*', $this->resource));
    }

    public function testMatchesWrongType(): void
    {
        $this->assertFalse($this->matcher->matches('user:*', $this->resource));
        $this->assertFalse($this->matcher->matches('user', $this->resource));
    }

    public function testMatchesWithIntegerId(): void
    {
        $resourceWithIntId = new class implements ResourceInterface {
            public function getResourceType(): string
            {
                return 'invoice';
            }

            public function getResourceId(): string|int
            {
                return 123; // int instead of string
            }

            public function getOwnerId(): string|int|null
            {
                return null;
            }

            public function getAttributes(): array
            {
                return [];
            }
        };

        $this->assertTrue($this->matcher->matches('invoice:123', $resourceWithIntId));
    }

    public function testMatchesMultipleColons(): void
    {
        $resource = new class implements ResourceInterface {
            public function getResourceType(): string
            {
                return 'invoice';
            }

            public function getResourceId(): string|int
            {
                return 'abc:def'; // ID with colon
            }

            public function getOwnerId(): string|int|null
            {
                return null;
            }

            public function getAttributes(): array
            {
                return [];
            }
        };

        // Should only split on first colon
        $this->assertTrue($this->matcher->matches('invoice:abc:def', $resource));
    }
}
