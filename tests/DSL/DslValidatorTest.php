<?php

declare(strict_types=1);

namespace Authza\Tests\DSL;

use Authza\DSL\DslValidator;
use Authza\DSL\LineDslParser;
use Authza\DSL\JsonDslParser;
use PHPUnit\Framework\TestCase;

class DslValidatorTest extends TestCase
{
    private DslValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new DslValidator();
    }

    public function testValidateValidDsl(): void
    {
        $dsl = "role:admin, invoice, create\nrole:accountant, invoice, view";
        $parser = new LineDslParser();

        $result = $this->validator->validate($parser, $dsl);

        $this->assertTrue($result->isValid());
        $this->assertEmpty($result->getErrors());
    }

    public function testValidateValidJson(): void
    {
        $json = '[
            {"subject": "role:admin", "resource": "invoice", "action": "create"},
            {"subject": "role:accountant", "resource": "invoice", "action": "view"}
        ]';
        $parser = new JsonDslParser();

        $result = $this->validator->validate($parser, $json);

        $this->assertTrue($result->isValid());
        $this->assertEmpty($result->getErrors());
    }

    public function testValidateEmptyContent(): void
    {
        $parser = new LineDslParser();

        $result = $this->validator->validate($parser, "");

        $this->assertTrue($result->isValid());
        $this->assertTrue($result->hasWarnings());
        $this->assertCount(1, $result->getWarnings());
        $this->assertStringContainsString("No rules found", $result->getWarnings()[0]);
    }

    public function testValidateInvalidSyntax(): void
    {
        $dsl = "invalid syntax without commas";
        $parser = new LineDslParser();

        $result = $this->validator->validate($parser, $dsl);

        $this->assertFalse($result->isValid());
        $this->assertNotEmpty($result->getErrors());
        $this->assertStringContainsString("Parse error", $result->getErrors()[0]);
    }

    public function testValidateInvalidSubjectFormat(): void
    {
        $dsl = "admin, invoice, create";
        $parser = new LineDslParser();

        $result = $this->validator->validate($parser, $dsl);

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString("Invalid subject format", $result->getErrors()[0]);
    }

    public function testValidateNonStandardResourceType(): void
    {
        $dsl = "role:admin, customresource, create";
        $parser = new LineDslParser();

        $result = $this->validator->validate($parser, $dsl);

        $this->assertTrue($result->isValid());
        $this->assertTrue($result->hasWarnings());
        $this->assertStringContainsString("Non-standard resource type", $result->getWarnings()[0]);
    }

    public function testValidateNonStandardAction(): void
    {
        $dsl = "role:admin, invoice, customaction";
        $parser = new LineDslParser();

        $result = $this->validator->validate($parser, $dsl);

        $this->assertTrue($result->isValid());
        $this->assertTrue($result->hasWarnings());
        $this->assertStringContainsString("Non-standard action", $result->getWarnings()[0]);
    }

    public function testValidateOwnerCondition(): void
    {
        $dsl = "user:*, invoice, edit, owner";
        $parser = new LineDslParser();

        $result = $this->validator->validate($parser, $dsl);

        $this->assertTrue($result->isValid());
        $this->assertEmpty($result->getWarnings());
    }

    public function testValidateEqualityCondition(): void
    {
        $dsl = "role:manager, invoice, approve, department==finance";
        $parser = new LineDslParser();

        $result = $this->validator->validate($parser, $dsl);

        $this->assertTrue($result->isValid());
    }

    public function testValidateInequalityCondition(): void
    {
        $dsl = "role:accountant, invoice, edit, status!=paid";
        $parser = new LineDslParser();

        $result = $this->validator->validate($parser, $dsl);

        $this->assertTrue($result->isValid());
    }

    public function testValidateUnknownConditionFormat(): void
    {
        $dsl = "role:admin, invoice, create, unknowncondition";
        $parser = new LineDslParser();

        $result = $this->validator->validate($parser, $dsl);

        $this->assertTrue($result->isValid());
        $this->assertTrue($result->hasWarnings());
        $this->assertStringContainsString("Unknown condition format", $result->getWarnings()[0]);
    }

    public function testValidateDuplicateRules(): void
    {
        $dsl = "role:admin, invoice, create\nrole:admin, invoice, create";
        $parser = new LineDslParser();

        $result = $this->validator->validate($parser, $dsl);

        $this->assertTrue($result->isValid());
        $this->assertTrue($result->hasWarnings());
        $this->assertStringContainsString("Duplicate rule", $result->getWarnings()[0]);
    }

    public function testValidateWildcardVsSpecificConflict(): void
    {
        $dsl = "user:*, invoice, edit, owner\nuser:42, invoice, edit, owner";
        $parser = new LineDslParser();

        $result = $this->validator->validate($parser, $dsl);

        $this->assertTrue($result->isValid());
        $this->assertTrue($result->hasWarnings());
        $this->assertStringContainsString("redundant", strtolower($result->getWarnings()[0]));
    }

    public function testValidateMultipleErrors(): void
    {
        $json = '[
            {"subject": "invalid", "resource": "invoice", "action": "create"},
            {"subject": "admin", "resource": "invoice", "action": "view"}
        ]';
        $parser = new JsonDslParser();

        $result = $this->validator->validate($parser, $json);

        $this->assertFalse($result->isValid());
        $this->assertGreaterThan(0, $result->getErrorCount());
    }

    public function testValidateStandardActions(): void
    {
        $actions = ['create', 'read', 'view', 'edit', 'update', 'delete', 'approve', 'reject'];

        foreach ($actions as $action) {
            $dsl = "role:admin, invoice, {$action}";
            $parser = new LineDslParser();

            $result = $this->validator->validate($parser, $dsl);

            $this->assertTrue($result->isValid(), "Action '{$action}' should be valid");
            $this->assertEmpty($result->getWarnings(), "Action '{$action}' should not have warnings");
        }
    }

    public function testValidateStandardResourceTypes(): void
    {
        $resources = ['invoice', 'client', 'user', 'order', 'product', 'report'];

        foreach ($resources as $resource) {
            $dsl = "role:admin, {$resource}, create";
            $parser = new LineDslParser();

            $result = $this->validator->validate($parser, $dsl);

            $this->assertTrue($result->isValid(), "Resource '{$resource}' should be valid");
            $this->assertEmpty($result->getWarnings(), "Resource '{$resource}' should not have warnings");
        }
    }

    public function testValidateEmptySubject(): void
    {
        $dsl = "role:, invoice, create";
        $parser = new LineDslParser();

        $result = $this->validator->validate($parser, $dsl);

        $this->assertFalse($result->isValid());
        // The parser catches this as an invalid format error
        $this->assertStringContainsString("Invalid subject format", $result->getErrors()[0]);
    }

    public function testValidationResultMethods(): void
    {
        $dsl = "admin, invoice, create\nrole:admin, customres, customaction";
        $parser = new LineDslParser();

        $result = $this->validator->validate($parser, $dsl);

        $this->assertFalse($result->isValid());
        $this->assertGreaterThan(0, $result->getErrorCount());
        $this->assertGreaterThanOrEqual(0, $result->getWarningCount());
        $this->assertIsArray($result->getErrors());
        $this->assertIsArray($result->getWarnings());
    }

    public function testValidateComplexRealWorldExample(): void
    {
        $dsl = <<<DSL
# Admin permissions
role:admin, invoice, create
role:admin, invoice, edit
role:admin, invoice, delete

# Accountant permissions
role:accountant, invoice, view
role:accountant, invoice, edit, status!=paid

# User permissions
user:*, invoice, view, owner
user:*, invoice, edit, owner

# Manager permissions
role:manager, invoice, approve, department==finance
DSL;
        $parser = new LineDslParser();

        $result = $this->validator->validate($parser, $dsl);

        $this->assertTrue($result->isValid());
    }

    public function testValidateJsonWithAllFeatures(): void
    {
        $json = '[
            {"subject": "role:admin", "resource": "invoice", "action": "create"},
            {"subject": "role:accountant", "resource": "invoice", "action": "view"},
            {"subject": "user:*", "resource": "invoice", "action": "edit", "condition": "owner"},
            {"subject": "role:manager", "resource": "invoice", "action": "approve", "condition": "department==finance"}
        ]';
        $parser = new JsonDslParser();

        $result = $this->validator->validate($parser, $json);

        $this->assertTrue($result->isValid());
    }

    public function testValidateResourceWithId(): void
    {
        $dsl = "user:42, invoice:123, delete, owner";
        $parser = new LineDslParser();

        $result = $this->validator->validate($parser, $dsl);

        $this->assertTrue($result->isValid());
    }

    public function testValidateWildcardAction(): void
    {
        $dsl = "role:admin, invoice, *";
        $parser = new LineDslParser();

        $result = $this->validator->validate($parser, $dsl);

        $this->assertTrue($result->isValid());
    }

    public function testValidateWildcardResource(): void
    {
        $dsl = "role:admin, *, create";
        $parser = new LineDslParser();

        $result = $this->validator->validate($parser, $dsl);

        $this->assertTrue($result->isValid());
    }
}
