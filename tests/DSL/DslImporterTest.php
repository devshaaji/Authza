<?php

declare(strict_types=1);

namespace Authza\Tests\DSL;

use Authza\Core\Graph\PermissionGraph;
use Authza\DSL\DslImporter;
use Authza\DSL\LineDslParser;
use Authza\DSL\JsonDslParser;
use Authza\Exceptions\DslParseException;
use Authza\Adapters\Cache\ArrayCache;
use PHPUnit\Framework\TestCase;

class DslImporterTest extends TestCase
{
    private PermissionGraph $graph;
    private DslImporter $importer;

    protected function setUp(): void
    {
        $this->graph = new PermissionGraph(new ArrayCache());
        $this->importer = new DslImporter($this->graph);
    }

    public function testImportLineDsl(): void
    {
        $dsl = "role:admin, invoice, create\nrole:accountant, invoice, view";
        $parser = new LineDslParser();

        $count = $this->importer->import($parser, $dsl);

        $this->assertEquals(2, $count);
        // DSL "role:admin" becomes subjectId "admin", resource "invoice" becomes resourceType "invoice", resourceId "*"
        $this->assertTrue($this->graph->check('role:admin', 'create', 'invoice', '*'));
        $this->assertTrue($this->graph->check('role:accountant', 'view', 'invoice', '*'));
    }

    public function testImportJsonDsl(): void
    {
        $json = '[
            {"subject": "role:admin", "resource": "invoice", "action": "create"},
            {"subject": "role:accountant", "resource": "invoice", "action": "view"}
        ]';
        $parser = new JsonDslParser();

        $count = $this->importer->import($parser, $json);

        $this->assertEquals(2, $count);
        $this->assertTrue($this->graph->check('role:admin', 'create', 'invoice', '*'));
        $this->assertTrue($this->graph->check('role:accountant', 'view', 'invoice', '*'));
    }

    public function testImportEmptyContent(): void
    {
        $parser = new LineDslParser();
        $count = $this->importer->import($parser, "");

        $this->assertEquals(0, $count);
    }

    public function testImportWithSpecificResourceId(): void
    {
        $dsl = "user:42, invoice:123, delete";
        $parser = new LineDslParser();

        $count = $this->importer->import($parser, $dsl);

        $this->assertEquals(1, $count);
        // user:42 becomes subjectId "42", invoice:123 becomes resourceType "invoice", resourceId "123"
        $this->assertTrue($this->graph->check('user:42', 'delete', 'invoice', '123'));
    }

    public function testImportFromFileJson(): void
    {
        $filePath = sys_get_temp_dir() . '/test_rules.json';
        $json = '[{"subject": "role:admin", "resource": "invoice", "action": "create"}]';
        file_put_contents($filePath, $json);

        $count = $this->importer->importFromFile($filePath);

        $this->assertEquals(1, $count);
        $this->assertTrue($this->graph->check('role:admin', 'create', 'invoice', '*'));

        unlink($filePath);
    }

    public function testImportFromFileDsl(): void
    {
        $filePath = sys_get_temp_dir() . '/test_rules.dsl';
        $dsl = "role:admin, invoice, create\nrole:accountant, invoice, view";
        file_put_contents($filePath, $dsl);

        $count = $this->importer->importFromFile($filePath);

        $this->assertEquals(2, $count);
        $this->assertTrue($this->graph->check('role:admin', 'create', 'invoice', '*'));
        $this->assertTrue($this->graph->check('role:accountant', 'view', 'invoice', '*'));

        unlink($filePath);
    }

    public function testImportFromFileTxt(): void
    {
        $filePath = sys_get_temp_dir() . '/test_rules.txt';
        $dsl = "role:admin, invoice, create";
        file_put_contents($filePath, $dsl);

        $count = $this->importer->importFromFile($filePath);

        $this->assertEquals(1, $count);
        $this->assertTrue($this->graph->check('role:admin', 'create', 'invoice', '*'));

        unlink($filePath);
    }

    public function testImportFromFileNotFound(): void
    {
        $this->expectException(DslParseException::class);
        $this->expectExceptionMessage("File not found");

        $this->importer->importFromFile('/nonexistent/file.json');
    }

    public function testImportFromFileUnsupportedFormat(): void
    {
        $filePath = sys_get_temp_dir() . '/test_rules.xml';
        file_put_contents($filePath, '<rules></rules>');

        $this->expectException(DslParseException::class);
        $this->expectExceptionMessage("Unsupported file format");

        try {
            $this->importer->importFromFile($filePath);
        } finally {
            unlink($filePath);
        }
    }

    public function testImportMultipleRules(): void
    {
        $dsl = <<<DSL
role:admin, invoice, create
role:admin, invoice, edit
role:admin, invoice, delete
role:accountant, invoice, view
role:accountant, invoice, edit
DSL;
        $parser = new LineDslParser();

        $count = $this->importer->import($parser, $dsl);

        $this->assertEquals(5, $count);
        // Verify a few permissions
        $this->assertTrue($this->graph->check('role:admin', 'create', 'invoice', '*'));
        $this->assertTrue($this->graph->check('role:admin', 'edit', 'invoice', '*'));
        $this->assertTrue($this->graph->check('role:accountant', 'view', 'invoice', '*'));
    }

    public function testImportInvalidDslThrowsException(): void
    {
        $this->expectException(DslParseException::class);

        $dsl = "invalid dsl content without proper format";
        $parser = new LineDslParser();

        $this->importer->import($parser, $dsl);
    }

    public function testImportRealExampleFile(): void
    {
        $exampleFile = __DIR__ . '/../../examples/dsl/rbac_rules.dsl';
        
        if (file_exists($exampleFile)) {
            $count = $this->importer->importFromFile($exampleFile);
            
            $this->assertGreaterThan(0, $count);
            $this->assertTrue($this->graph->check('role:admin', 'create', 'invoice', '*'));
            $this->assertTrue($this->graph->check('role:accountant', 'view', 'invoice', '*'));
        } else {
            $this->markTestSkipped('Example file not found');
        }
    }
}
