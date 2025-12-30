<?php

declare(strict_types=1);

namespace Authza\Tests\DSL;

use Authza\Core\Graph\PermissionGraph;
use Authza\DSL\DslExporter;
use Authza\DSL\DslImporter;
use Authza\DSL\LineDslParser;
use Authza\DSL\JsonDslParser;
use Authza\Adapters\Cache\ArrayCache;
use PHPUnit\Framework\TestCase;

class DslExporterTest extends TestCase
{
    private PermissionGraph $graph;
    private DslExporter $exporter;

    protected function setUp(): void
    {
        $this->graph = new PermissionGraph(new ArrayCache());
        $this->exporter = new DslExporter($this->graph);
    }

    public function testExportToJson(): void
    {
        $this->graph->addPermission('role:admin', 'invoice', 'create', null);
        $this->graph->addPermission('role:accountant', 'invoice', 'view', null);

        $json = $this->exporter->export('json');

        $this->assertIsString($json);
        $data = json_decode($json, true);
        $this->assertIsArray($data);
        $this->assertCount(2, $data);
        $this->assertEquals('role:admin', $data[0]['subject']);
        $this->assertEquals('role:accountant', $data[1]['subject']);
    }

    public function testExportToLine(): void
    {
        $this->graph->addPermission('role:admin', 'invoice', 'create', null);
        $this->graph->addPermission('role:accountant', 'invoice', 'view', null);

        $line = $this->exporter->export('line');

        $this->assertIsString($line);
        $lines = explode("\n", trim($line));
        $this->assertCount(2, $lines);
        $this->assertStringContainsString('role:admin', $lines[0]);
        $this->assertStringContainsString('invoice', $lines[0]);
        $this->assertStringContainsString('create', $lines[0]);
    }

    public function testExportEmptyGraph(): void
    {
        $json = $this->exporter->export('json');
        $this->assertEquals('[]', $json);

        $line = $this->exporter->export('line');
        $this->assertEquals('', $line);
    }

    public function testExportWithConditions(): void
    {
        $this->graph->addPermission('user:*', 'invoice', 'edit', 'owner');

        $json = $this->exporter->export('json');
        $data = json_decode($json, true);

        $this->assertCount(1, $data);
        $this->assertEquals('owner', $data[0]['condition']);
    }

    public function testExportWithoutConditions(): void
    {
        $this->graph->addPermission('role:admin', 'invoice', 'create', null);

        $json = $this->exporter->export('json');
        $data = json_decode($json, true);

        $this->assertCount(1, $data);
        $this->assertArrayNotHasKey('condition', $data[0]);
    }

    public function testExportLineFormatWithCondition(): void
    {
        $this->graph->addPermission('user:42', 'invoice', 'edit', 'owner');

        $line = $this->exporter->export('line');

        $this->assertStringContainsString('user:42, invoice, edit, owner', $line);
    }

    public function testExportLineFormatWithoutCondition(): void
    {
        $this->graph->addPermission('role:admin', 'invoice', 'create', null);

        $line = $this->exporter->export('line');

        $this->assertStringContainsString('role:admin, invoice, create', $line);
        $this->assertStringNotContainsString('null', $line);
    }

    public function testExportToFileJson(): void
    {
        $this->graph->addPermission('role:admin', 'invoice', 'create', null);

        $filePath = sys_get_temp_dir() . '/export_test.json';
        $result = $this->exporter->exportToFile($filePath, 'json');

        $this->assertTrue($result);
        $this->assertFileExists($filePath);

        $content = file_get_contents($filePath);
        $data = json_decode($content, true);
        $this->assertCount(1, $data);

        unlink($filePath);
    }

    public function testExportToFileLine(): void
    {
        $this->graph->addPermission('role:admin', 'invoice', 'create', null);

        $filePath = sys_get_temp_dir() . '/export_test.dsl';
        $result = $this->exporter->exportToFile($filePath, 'line');

        $this->assertTrue($result);
        $this->assertFileExists($filePath);

        $content = file_get_contents($filePath);
        $this->assertStringContainsString('role:admin', $content);

        unlink($filePath);
    }

    public function testExportToFileCreatesDirectory(): void
    {
        $this->graph->addPermission('role:admin', 'invoice', 'create', null);

        $dir = sys_get_temp_dir() . '/authza_test_' . uniqid();
        $filePath = $dir . '/export_test.json';

        $result = $this->exporter->exportToFile($filePath, 'json');

        $this->assertTrue($result);
        $this->assertFileExists($filePath);

        unlink($filePath);
        rmdir($dir);
    }

    public function testExportUnsupportedFormat(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Unsupported format");

        $this->exporter->export('xml');
    }

    public function testRoundTripJsonImportExport(): void
    {
        $originalDsl = '[
            {"subject": "role:admin", "resource": "invoice", "action": "create"},
            {"subject": "role:accountant", "resource": "invoice", "action": "view", "condition": "owner"}
        ]';

        $importer = new DslImporter($this->graph);
        $parser = new JsonDslParser();
        $importer->import($parser, $originalDsl);

        $exported = $this->exporter->export('json');
        $exportedData = json_decode($exported, true);

        $this->assertCount(2, $exportedData);
        $this->assertEquals('role:admin', $exportedData[0]['subject']);
        $this->assertEquals('invoice', $exportedData[0]['resource']);
        $this->assertEquals('create', $exportedData[0]['action']);
        $this->assertEquals('role:accountant', $exportedData[1]['subject']);
        $this->assertEquals('owner', $exportedData[1]['condition']);
    }

    public function testRoundTripLineImportExport(): void
    {
        $originalDsl = "role:admin, invoice, create\nrole:accountant, invoice, view, owner";

        $importer = new DslImporter($this->graph);
        $parser = new LineDslParser();
        $importer->import($parser, $originalDsl);

        $exported = $this->exporter->export('line');
        $lines = explode("\n", trim($exported));

        $this->assertCount(2, $lines);
        $this->assertStringContainsString('role:admin, invoice, create', $lines[0]);
        $this->assertStringContainsString('role:accountant, invoice, view, owner', $lines[1]);
    }

    public function testExportMultiplePermissions(): void
    {
        $this->graph->addPermission('role:admin', 'invoice', 'create', null);
        $this->graph->addPermission('role:admin', 'invoice', 'edit', null);
        $this->graph->addPermission('role:admin', 'invoice', 'delete', null);
        $this->graph->addPermission('role:accountant', 'invoice', 'view', null);
        $this->graph->addPermission('user:*', 'invoice', 'edit', 'owner');

        $json = $this->exporter->export('json');
        $data = json_decode($json, true);

        $this->assertCount(5, $data);
    }

    public function testExportJsonIsValidJson(): void
    {
        $this->graph->addPermission('role:admin', 'invoice', 'create', null);

        $json = $this->exporter->export('json');

        $this->assertNotFalse(json_decode($json));
        $this->assertEquals(JSON_ERROR_NONE, json_last_error());
    }

    public function testExportLineFormatEndsWithNewline(): void
    {
        $this->graph->addPermission('role:admin', 'invoice', 'create', null);

        $line = $this->exporter->export('line');

        $this->assertStringEndsWith("\n", $line);
    }

    public function testExportContextConditions(): void
    {
        $this->graph->addPermission('role:manager', 'invoice', 'approve', 'department==finance');

        $json = $this->exporter->export('json');
        $data = json_decode($json, true);

        $this->assertEquals('department==finance', $data[0]['condition']);

        $line = $this->exporter->export('line');
        $this->assertStringContainsString('department==finance', $line);
    }
}
