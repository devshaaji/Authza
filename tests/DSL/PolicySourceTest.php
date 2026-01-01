<?php

declare(strict_types=1);

namespace Authza\Tests\DSL;

use Authza\DSL\DslPolicySource;
use Authza\DSL\DslValidator;
use Authza\DSL\JsonDslParser;
use Authza\DSL\LineDslParser;
use Authza\DSL\PolicyDefinition;
use Authza\Exceptions\DslParseException;
use PHPUnit\Framework\TestCase;

class PolicySourceTest extends TestCase
{
    public function testLoadFromStringWithJsonParser(): void
    {
        $content = '[
            {"subject": "role:admin", "resource": "invoice", "action": "create"},
            {"subject": "user:42", "resource": "invoice:123", "action": "delete", "condition": "owner"}
        ]';

        $source = new DslPolicySource(new JsonDslParser());
        $policies = $source->loadFromString($content);

        $this->assertCount(2, $policies);
        $this->assertInstanceOf(PolicyDefinition::class, $policies[0]);
        $this->assertEquals('role:admin', $policies[0]->subject);
        $this->assertEquals('invoice', $policies[0]->resource);
        $this->assertEquals('create', $policies[0]->action);
        $this->assertNull($policies[0]->condition);
        $this->assertEquals('allow', $policies[0]->effect);
    }

    public function testLoadFromStringWithLineDslParser(): void
    {
        $content = "role:admin, invoice, create\nuser:42, invoice:123, delete, owner";

        $source = new DslPolicySource(new LineDslParser());
        $policies = $source->loadFromString($content);

        $this->assertCount(2, $policies);
        $this->assertInstanceOf(PolicyDefinition::class, $policies[0]);
        $this->assertEquals('role:admin', $policies[0]->subject);
        $this->assertEquals('user:42', $policies[1]->subject);
        $this->assertEquals('owner', $policies[1]->condition);
    }

    public function testLoadFromStringWithValidator(): void
    {
        $content = '[
            {"subject": "role:admin", "resource": "invoice", "action": "create"}
        ]';

        $validator = new DslValidator();
        $source = new DslPolicySource(new JsonDslParser(), $validator);
        $policies = $source->loadFromString($content);

        $this->assertCount(1, $policies);
        $this->assertInstanceOf(PolicyDefinition::class, $policies[0]);
    }

    public function testLoadFromFile(): void
    {
        $filePath = sys_get_temp_dir() . '/test_policy.json';
        file_put_contents($filePath, '[
            {"subject": "role:admin", "resource": "invoice", "action": "create"}
        ]');

        try {
            $source = new DslPolicySource(new JsonDslParser());
            $policies = $source->loadFromFile($filePath);

            $this->assertCount(1, $policies);
            $this->assertInstanceOf(PolicyDefinition::class, $policies[0]);
        } finally {
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
    }

    public function testLoadFromFileNotFound(): void
    {
        $this->expectException(DslParseException::class);
        $this->expectExceptionMessage('File not found');

        $source = new DslPolicySource(new JsonDslParser());
        $source->loadFromFile('/nonexistent/file.json');
    }

    public function testLoadDirectlyThrowsException(): void
    {
        $this->expectException(\BadMethodCallException::class);
        
        $source = new DslPolicySource(new JsonDslParser());
        $source->load();
    }
}
