<?php

declare(strict_types=1);

namespace Authza\Tests\DSL;

use Authza\DSL\LineDslParser;
use Authza\Exceptions\DslParseException;
use PHPUnit\Framework\TestCase;

class LineDslParserTest extends TestCase
{
    private LineDslParser $parser;

    protected function setUp(): void
    {
        $this->parser = new LineDslParser();
    }

    public function testParseValidLineDsl(): void
    {
        $dsl = "role:admin, invoice, create\nrole:accountant, invoice, view";
        $rules = $this->parser->parse($dsl);

        $this->assertCount(2, $rules);
        $this->assertEquals('role:admin', $rules[0]['subject']);
        $this->assertEquals('invoice', $rules[0]['resource']);
        $this->assertEquals('create', $rules[0]['action']);
        $this->assertNull($rules[0]['condition']);
    }

    public function testParseWithCondition(): void
    {
        $dsl = "user:42, invoice:123, delete, owner";
        $rules = $this->parser->parse($dsl);

        $this->assertCount(1, $rules);
        $this->assertEquals('user:42', $rules[0]['subject']);
        $this->assertEquals('invoice:123', $rules[0]['resource']);
        $this->assertEquals('delete', $rules[0]['action']);
        $this->assertEquals('owner', $rules[0]['condition']);
    }

    public function testParseWithWildcard(): void
    {
        $dsl = "user:*, invoice, edit, owner";
        $rules = $this->parser->parse($dsl);

        $this->assertCount(1, $rules);
        $this->assertEquals('user:*', $rules[0]['subject']);
    }

    public function testParseWithContextCondition(): void
    {
        $dsl = "role:manager, invoice, approve, department==finance";
        $rules = $this->parser->parse($dsl);

        $this->assertCount(1, $rules);
        $this->assertEquals('department==finance', $rules[0]['condition']);
    }

    public function testSkipEmptyLines(): void
    {
        $dsl = "role:admin, invoice, create\n\nrole:accountant, invoice, view\n\n";
        $rules = $this->parser->parse($dsl);

        $this->assertCount(2, $rules);
    }

    public function testSkipComments(): void
    {
        $dsl = "# This is a comment\nrole:admin, invoice, create\n# Another comment\nrole:accountant, invoice, view";
        $rules = $this->parser->parse($dsl);

        $this->assertCount(2, $rules);
    }

    public function testTrimWhitespace(): void
    {
        $dsl = "  role:admin  ,  invoice  ,  create  ";
        $rules = $this->parser->parse($dsl);

        $this->assertCount(1, $rules);
        $this->assertEquals('role:admin', $rules[0]['subject']);
        $this->assertEquals('invoice', $rules[0]['resource']);
        $this->assertEquals('create', $rules[0]['action']);
    }

    public function testEmptyContent(): void
    {
        $rules = $this->parser->parse("");
        $this->assertEmpty($rules);
    }

    public function testOnlyCommentsAndEmptyLines(): void
    {
        $dsl = "# Comment 1\n\n# Comment 2\n";
        $rules = $this->parser->parse($dsl);
        $this->assertEmpty($rules);
    }

    public function testInvalidSyntaxTooFewFields(): void
    {
        $this->expectException(DslParseException::class);
        $this->expectExceptionMessage("Invalid DSL syntax");
        
        $dsl = "role:admin, invoice";
        $this->parser->parse($dsl);
    }

    public function testInvalidSyntaxEmptySubject(): void
    {
        $this->expectException(DslParseException::class);
        $this->expectExceptionMessage("Subject cannot be empty");
        
        $dsl = ", invoice, create";
        $this->parser->parse($dsl);
    }

    public function testInvalidSyntaxEmptyResource(): void
    {
        $this->expectException(DslParseException::class);
        $this->expectExceptionMessage("Resource cannot be empty");
        
        $dsl = "role:admin, , create";
        $this->parser->parse($dsl);
    }

    public function testInvalidSyntaxEmptyAction(): void
    {
        $this->expectException(DslParseException::class);
        $this->expectExceptionMessage("Action cannot be empty");
        
        $dsl = "role:admin, invoice, ";
        $this->parser->parse($dsl);
    }

    public function testInvalidSubjectFormat(): void
    {
        $this->expectException(DslParseException::class);
        $this->expectExceptionMessage("Invalid subject format");
        
        $dsl = "admin, invoice, create";
        $this->parser->parse($dsl);
    }

    public function testInvalidSubjectFormatNoColon(): void
    {
        $this->expectException(DslParseException::class);
        $this->expectExceptionMessage("Invalid subject format");
        
        $dsl = "roleadmin, invoice, create";
        $this->parser->parse($dsl);
    }

    public function testExceptionIncludesLineNumber(): void
    {
        try {
            $dsl = "role:admin, invoice, create\ninvalid line\nrole:accountant, invoice, view";
            $this->parser->parse($dsl);
            $this->fail("Expected DslParseException");
        } catch (DslParseException $e) {
            $this->assertEquals(2, $e->getLineNumber());
            $this->assertEquals("invalid line", $e->getLineContent());
        }
    }

    public function testParseMultipleRulesWithMixedContent(): void
    {
        $dsl = <<<DSL
# Admin permissions
role:admin, invoice, create
role:admin, invoice, edit

# Accountant permissions
role:accountant, invoice, view

# User permissions with conditions
user:*, invoice, edit, owner
DSL;

        $rules = $this->parser->parse($dsl);

        $this->assertCount(4, $rules);
        $this->assertEquals('role:admin', $rules[0]['subject']);
        $this->assertEquals('role:admin', $rules[1]['subject']);
        $this->assertEquals('role:accountant', $rules[2]['subject']);
        $this->assertEquals('user:*', $rules[3]['subject']);
        $this->assertEquals('owner', $rules[3]['condition']);
    }

    public function testParseWithEmptyConditionField(): void
    {
        $dsl = "role:admin, invoice, create, ";
        $rules = $this->parser->parse($dsl);

        $this->assertCount(1, $rules);
        $this->assertNull($rules[0]['condition']);
    }
}
