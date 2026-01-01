<?php

declare(strict_types=1);

namespace Authza\Tests\Condition;

use Authza\Condition\ConditionResolverChain;
use Authza\Condition\Resolvers\AttributeConditionResolver;
use Authza\Condition\Resolvers\OwnerConditionResolver;
use Authza\Interfaces\ResourceInterface;
use Authza\Interfaces\SubjectInterface;
use PHPUnit\Framework\TestCase;

class ConditionResolverTest extends TestCase
{
    private SubjectInterface $user;
    private ResourceInterface $resource;

    protected function setUp(): void
    {
        // Create mock user
        $this->user = new class implements SubjectInterface {
            public function getId(): string|int
            {
                return '42';
            }

            public function getRoles(): array
            {
                return ['admin'];
            }

            public function getAttributes(): array
            {
                return ['department' => 'finance'];
            }
        };

        // Create mock resource
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
                return ['status' => 'pending'];
            }
        };
    }

    public function testOwnerConditionResolverSupports(): void
    {
        $resolver = new OwnerConditionResolver();
        
        $this->assertTrue($resolver->supports('owner'));
        $this->assertFalse($resolver->supports('department==finance'));
    }

    public function testOwnerConditionResolverEvaluate(): void
    {
        $resolver = new OwnerConditionResolver();
        
        // User is the owner
        $this->assertTrue($resolver->evaluate('owner', $this->user, $this->resource));
    }

    public function testOwnerConditionResolverNotOwner(): void
    {
        $resolver = new OwnerConditionResolver();
        
        // Different owner
        $resource = new class implements ResourceInterface {
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
                return '999'; // Different owner
            }

            public function getAttributes(): array
            {
                return [];
            }
        };
        
        $this->assertFalse($resolver->evaluate('owner', $this->user, $resource));
    }

    public function testAttributeConditionResolverSupports(): void
    {
        $resolver = new AttributeConditionResolver();
        
        $this->assertTrue($resolver->supports('department==finance'));
        $this->assertTrue($resolver->supports('status!=approved'));
        $this->assertFalse($resolver->supports('owner'));
    }

    public function testAttributeConditionResolverEqualityFromUserAttributes(): void
    {
        $resolver = new AttributeConditionResolver();
        
        // Check user attribute
        $this->assertTrue($resolver->evaluate('department==finance', $this->user, $this->resource));
        $this->assertFalse($resolver->evaluate('department==sales', $this->user, $this->resource));
    }

    public function testAttributeConditionResolverEqualityFromResourceAttributes(): void
    {
        $resolver = new AttributeConditionResolver();
        
        // Check resource attribute
        $this->assertTrue($resolver->evaluate('status==pending', $this->user, $this->resource));
        $this->assertFalse($resolver->evaluate('status==approved', $this->user, $this->resource));
    }

    public function testAttributeConditionResolverInequality(): void
    {
        $resolver = new AttributeConditionResolver();
        
        $this->assertTrue($resolver->evaluate('department!=sales', $this->user, $this->resource));
        $this->assertFalse($resolver->evaluate('department!=finance', $this->user, $this->resource));
    }

    public function testAttributeConditionResolverFromContext(): void
    {
        $resolver = new AttributeConditionResolver();
        
        $context = ['region' => 'north'];
        
        $this->assertTrue($resolver->evaluate('region==north', $this->user, $this->resource, $context));
        $this->assertFalse($resolver->evaluate('region==south', $this->user, $this->resource, $context));
    }

    public function testConditionResolverChain(): void
    {
        $chain = new ConditionResolverChain([
            new OwnerConditionResolver(),
            new AttributeConditionResolver(),
        ]);

        // Test owner condition
        $this->assertTrue($chain->supports('owner'));
        $this->assertTrue($chain->evaluate('owner', $this->user, $this->resource));

        // Test attribute condition
        $this->assertTrue($chain->supports('department==finance'));
        $this->assertTrue($chain->evaluate('department==finance', $this->user, $this->resource));
    }

    public function testConditionResolverChainUnsupportedCondition(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No resolver found for condition');

        $chain = new ConditionResolverChain([
            new OwnerConditionResolver(),
        ]);

        $chain->evaluate('unsupported==condition', $this->user, $this->resource);
    }

    public function testConditionResolverChainAddResolver(): void
    {
        $chain = new ConditionResolverChain();
        
        $this->assertFalse($chain->supports('owner'));
        
        $chain->addResolver(new OwnerConditionResolver());
        
        $this->assertTrue($chain->supports('owner'));
    }
}
