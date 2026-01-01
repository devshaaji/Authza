<?php

declare(strict_types=1);

namespace Authza\Tests\DSL;

use Authza\DSL\DslExporter;
use Authza\DSL\PolicyDefinition;
use PHPUnit\Framework\TestCase;

class DslExporterTest extends TestCase
{
    public function testExportToJson(): void
    {
        $policies = [
            new PolicyDefinition('role:admin', 'invoice', 'create'),
            new PolicyDefinition('user:42', 'invoice:123', 'delete', 'owner'),
        ];

        $exporter = new DslExporter($policies);
        $json = $exporter->export('json');

        $data = json_decode($json, true);
        $this->assertCount(2, $data);
        $this->assertEquals('role:admin', $data[0]['subject']);
        $this->assertEquals('invoice', $data[0]['resource']);
        $this->assertEquals('create', $data[0]['action']);
        $this->assertEquals('allow', $data[0]['effect']);
    }

    public function testExportToLine(): void
    {
        $policies = [
            new PolicyDefinition('role:admin', 'invoice', 'create'),
            new PolicyDefinition('user:42', 'invoice:123', 'delete', 'owner'),
        ];

        $exporter = new DslExporter($policies);
        $content = $exporter->export('line');

        $lines = explode("\n", $content);
        $this->assertCount(2, $lines);
        $this->assertEquals('role:admin, invoice, create', $lines[0]);
        $this->assertEquals('user:42, invoice:123, delete, owner', $lines[1]);
    }

    public function testExportToLineWithDenyEffect(): void
    {
        $policies = [
            new PolicyDefinition('role:admin', 'invoice', 'delete', null, 'deny'),
        ];

        $exporter = new DslExporter($policies);
        $content = $exporter->export('line');

        $this->assertEquals('role:admin, invoice, delete, , deny', $content);
    }

    public function testExportToFile(): void
    {
        $policies = [
            new PolicyDefinition('role:admin', 'invoice', 'create'),
        ];

        $filePath = sys_get_temp_dir() . '/test_export.json';

        try {
            $exporter = new DslExporter($policies);
            $result = $exporter->exportToFile($filePath, 'json');

            $this->assertTrue($result);
            $this->assertFileExists($filePath);

            $content = file_get_contents($filePath);
            $data = json_decode($content, true);
            $this->assertCount(1, $data);
            $this->assertEquals('role:admin', $data[0]['subject']);
        } finally {
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
    }

    public function testExportUnsupportedFormat(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported export format');

        $exporter = new DslExporter([]);
        $exporter->export('xml');
    }

    public function testSetPolicies(): void
    {
        $exporter = new DslExporter([]);
        
        $policies = [
            new PolicyDefinition('role:admin', 'invoice', 'create'),
        ];
        
        $exporter->setPolicies($policies);
        $json = $exporter->export('json');

        $data = json_decode($json, true);
        $this->assertCount(1, $data);
    }

    public function testExportEmptyPolicies(): void
    {
        $exporter = new DslExporter([]);
        $json = $exporter->export('json');

        $data = json_decode($json, true);
        $this->assertEmpty($data);
    }
}
