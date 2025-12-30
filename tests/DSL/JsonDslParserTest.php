<?php

declare(strict_types=1);

namespace Authza\Tests\DSL;

use Authza\DSL\JsonDslParser;
use Authza\Exceptions\DslParseException;
use PHPUnit\Framework\TestCase;

class JsonDslParserTest extends TestCase
{
    private JsonDslParser $parser;

    protected function setUp(): void
    {
        $this->parser = new JsonDslParser();
    }

    public function testParseValidJson(): void
    {
        $json = '[
            {"subject": "role:admin", "resource": "invoice", "action": "create"},
            {"subject": "role:accountant", "resource": "invoice", "action": "view"}
        ]';

        $rules = $this->parser->parse($json);

        $this->assertCount(2, $rules);
        $this->assertEquals('role:admin', $rules[0]['subject']);
        $this->assertEquals('invoice', $rules[0]['resource']);
        $this->assertEquals('create', $rules[0]['action']);
        $this->assertNull($rules[0]['condition']);
    }

    public function testParseWithCondition(): void
    {
        $json = '[
            {"subject": "user:42", "resource": "invoice:123", "action": "delete", "condition": "owner"}
        ]';

        $rules = $this->parser->parse($json);

        $this->assertCount(1, $rules);
        $this->assertEquals('owner', $rules[0]['condition']);
    }

    public function testParseWithoutCondition(): void
    {
        $json = '[
            {"subject": "role:admin", "resource": "invoice", "action": "create"}
        ]';

        $rules = $this->parser->parse($json);

        $this->assertCount(1, $rules);
        $this->assertNull($rules[0]['condition']);
    }

    public function testParseEmptyArray(): void
    {
        $json = '[]';
        $rules = $this->parser->parse($json);

        $this->assertEmpty($rules);
    }

    public function testParseEmptyString(): void
    {
        $rules = $this->parser->parse('');
        $this->assertEmpty($rules);
    }

    public function testParseWhitespaceOnly(): void
    {
        $rules = $this->parser->parse("  \n  \t  ");
        $this->assertEmpty($rules);
    }

    public function testInvalidJson(): void
    {
        $this->expectException(DslParseException::class);
        $this->expectExceptionMessage("Invalid JSON");

        $json = '{invalid json}';
        $this->parser->parse($json);
    }

    public function testInvalidJsonStructureNotArray(): void
    {
        $this->expectException(DslParseException::class);
        $this->expectExceptionMessage("Expected array of permission rules");

        $json = '{"subject": "role:admin", "resource": "invoice", "action": "create"}';
        $this->parser->parse($json);
    }

    public function testInvalidRuleNotObject(): void
    {
        $this->expectException(DslParseException::class);
        $this->expectExceptionMessage("Expected object");

        $json = '["role:admin", "invoice", "create"]';
        $this->parser->parse($json);
    }

    public function testMissingSubject(): void
    {
        $this->expectException(DslParseException::class);
        $this->expectExceptionMessage("Missing required field 'subject'");

        $json = '[{"resource": "invoice", "action": "create"}]';
        $this->parser->parse($json);
    }

    public function testMissingResource(): void
    {
        $this->expectException(DslParseException::class);
        $this->expectExceptionMessage("Missing required field 'resource'");

        $json = '[{"subject": "role:admin", "action": "create"}]';
        $this->parser->parse($json);
    }

    public function testMissingAction(): void
    {
        $this->expectException(DslParseException::class);
        $this->expectExceptionMessage("Missing required field 'action'");

        $json = '[{"subject": "role:admin", "resource": "invoice"}]';
        $this->parser->parse($json);
    }

    public function testEmptySubject(): void
    {
        $this->expectException(DslParseException::class);
        $this->expectExceptionMessage("Invalid 'subject'");

        $json = '[{"subject": "", "resource": "invoice", "action": "create"}]';
        $this->parser->parse($json);
    }

    public function testEmptyResource(): void
    {
        $this->expectException(DslParseException::class);
        $this->expectExceptionMessage("Invalid 'resource'");

        $json = '[{"subject": "role:admin", "resource": "", "action": "create"}]';
        $this->parser->parse($json);
    }

    public function testEmptyAction(): void
    {
        $this->expectException(DslParseException::class);
        $this->expectExceptionMessage("Invalid 'action'");

        $json = '[{"subject": "role:admin", "resource": "invoice", "action": ""}]';
        $this->parser->parse($json);
    }

    public function testInvalidSubjectType(): void
    {
        $this->expectException(DslParseException::class);
        $this->expectExceptionMessage("Invalid 'subject'");

        $json = '[{"subject": 123, "resource": "invoice", "action": "create"}]';
        $this->parser->parse($json);
    }

    public function testInvalidSubjectFormat(): void
    {
        $this->expectException(DslParseException::class);
        $this->expectExceptionMessage("Invalid subject format");

        $json = '[{"subject": "admin", "resource": "invoice", "action": "create"}]';
        $this->parser->parse($json);
    }

    public function testInvalidConditionEmpty(): void
    {
        $this->expectException(DslParseException::class);
        $this->expectExceptionMessage("Invalid 'condition'");

        $json = '[{"subject": "role:admin", "resource": "invoice", "action": "create", "condition": ""}]';
        $this->parser->parse($json);
    }

    public function testInvalidConditionType(): void
    {
        $this->expectException(DslParseException::class);
        $this->expectExceptionMessage("Invalid 'condition'");

        $json = '[{"subject": "role:admin", "resource": "invoice", "action": "create", "condition": 123}]';
        $this->parser->parse($json);
    }

    public function testErrorMessageIncludesIndex(): void
    {
        try {
            $json = '[
                {"subject": "role:admin", "resource": "invoice", "action": "create"},
                {"subject": "invalid", "resource": "invoice", "action": "view"}
            ]';
            $this->parser->parse($json);
            $this->fail("Expected DslParseException");
        } catch (DslParseException $e) {
            $this->assertStringContainsString("rule index 1", $e->getMessage());
        }
    }

    public function testParseComplexExample(): void
    {
        $json = '[
            {"subject": "role:admin", "resource": "invoice", "action": "create"},
            {"subject": "role:accountant", "resource": "invoice", "action": "view"},
            {"subject": "user:42", "resource": "invoice:123", "action": "delete", "condition": "owner"},
            {"subject": "role:manager", "resource": "invoice", "action": "approve", "condition": "department==finance"}
        ]';

        $rules = $this->parser->parse($json);

        $this->assertCount(4, $rules);
        $this->assertEquals('role:admin', $rules[0]['subject']);
        $this->assertEquals('role:accountant', $rules[1]['subject']);
        $this->assertEquals('user:42', $rules[2]['subject']);
        $this->assertEquals('owner', $rules[2]['condition']);
        $this->assertEquals('role:manager', $rules[3]['subject']);
        $this->assertEquals('department==finance', $rules[3]['condition']);
    }

    public function testParseWithWildcards(): void
    {
        $json = '[
            {"subject": "user:*", "resource": "invoice", "action": "edit", "condition": "owner"}
        ]';

        $rules = $this->parser->parse($json);

        $this->assertCount(1, $rules);
        $this->assertEquals('user:*', $rules[0]['subject']);
    }
}
