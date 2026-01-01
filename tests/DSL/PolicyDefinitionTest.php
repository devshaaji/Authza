<?php

declare(strict_types=1);

namespace Authza\Tests\DSL;

use Authza\DSL\PolicyDefinition;
use PHPUnit\Framework\TestCase;

class PolicyDefinitionTest extends TestCase
{
    public function testConstructor(): void
    {
        $policy = new PolicyDefinition(
            subject: 'role:admin',
            resource: 'invoice',
            action: 'create'
        );

        $this->assertEquals('role:admin', $policy->subject);
        $this->assertEquals('invoice', $policy->resource);
        $this->assertEquals('create', $policy->action);
        $this->assertNull($policy->condition);
        $this->assertEquals('allow', $policy->effect);
    }

    public function testConstructorWithAllFields(): void
    {
        $policy = new PolicyDefinition(
            subject: 'user:42',
            resource: 'invoice:123',
            action: 'delete',
            condition: 'owner',
            effect: 'deny'
        );

        $this->assertEquals('user:42', $policy->subject);
        $this->assertEquals('invoice:123', $policy->resource);
        $this->assertEquals('delete', $policy->action);
        $this->assertEquals('owner', $policy->condition);
        $this->assertEquals('deny', $policy->effect);
    }

    public function testConstructorInvalidEffect(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Effect must be 'allow' or 'deny'");

        new PolicyDefinition(
            subject: 'role:admin',
            resource: 'invoice',
            action: 'create',
            effect: 'invalid'
        );
    }

    public function testFromArray(): void
    {
        $data = [
            'subject' => 'role:admin',
            'resource' => 'invoice',
            'action' => 'create',
        ];

        $policy = PolicyDefinition::fromArray($data);

        $this->assertEquals('role:admin', $policy->subject);
        $this->assertEquals('invoice', $policy->resource);
        $this->assertEquals('create', $policy->action);
        $this->assertNull($policy->condition);
        $this->assertEquals('allow', $policy->effect);
    }

    public function testFromArrayWithAllFields(): void
    {
        $data = [
            'subject' => 'user:42',
            'resource' => 'invoice:123',
            'action' => 'delete',
            'condition' => 'owner',
            'effect' => 'deny',
        ];

        $policy = PolicyDefinition::fromArray($data);

        $this->assertEquals('user:42', $policy->subject);
        $this->assertEquals('invoice:123', $policy->resource);
        $this->assertEquals('delete', $policy->action);
        $this->assertEquals('owner', $policy->condition);
        $this->assertEquals('deny', $policy->effect);
    }

    public function testFromArrayMissingSubject(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Missing required field 'subject'");

        PolicyDefinition::fromArray([
            'resource' => 'invoice',
            'action' => 'create',
        ]);
    }

    public function testFromArrayMissingResource(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Missing required field 'resource'");

        PolicyDefinition::fromArray([
            'subject' => 'role:admin',
            'action' => 'create',
        ]);
    }

    public function testFromArrayMissingAction(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Missing required field 'action'");

        PolicyDefinition::fromArray([
            'subject' => 'role:admin',
            'resource' => 'invoice',
        ]);
    }

    public function testToArray(): void
    {
        $policy = new PolicyDefinition(
            subject: 'role:admin',
            resource: 'invoice',
            action: 'create'
        );

        $data = $policy->toArray();

        $this->assertEquals([
            'subject' => 'role:admin',
            'resource' => 'invoice',
            'action' => 'create',
            'effect' => 'allow',
        ], $data);
    }

    public function testToArrayWithAllFields(): void
    {
        $policy = new PolicyDefinition(
            subject: 'user:42',
            resource: 'invoice:123',
            action: 'delete',
            condition: 'owner',
            effect: 'deny'
        );

        $data = $policy->toArray();

        $this->assertEquals([
            'subject' => 'user:42',
            'resource' => 'invoice:123',
            'action' => 'delete',
            'effect' => 'deny',
            'condition' => 'owner',
        ], $data);
    }
}
