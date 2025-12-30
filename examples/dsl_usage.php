<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Authza\Core\Graph\PermissionGraph;
use Authza\DSL\LineDslParser;
use Authza\DSL\JsonDslParser;
use Authza\DSL\DslImporter;
use Authza\DSL\DslValidator;
use Authza\DSL\DslExporter;
use Authza\Adapters\Cache\ArrayCache;

echo "=== Authza DSL Usage Example ===\n\n";

// Initialize
$cache = new ArrayCache();
$graph = new PermissionGraph($cache);

// Example 1: Validate and import line-based DSL
echo "1. Validating and importing RBAC rules from DSL file...\n";
$validator = new DslValidator();
$dslContent = file_get_contents(__DIR__ . '/dsl/rbac_rules.dsl');
$parser = new LineDslParser();
$result = $validator->validate($parser, $dslContent);

if (!$result->isValid()) {
    echo "DSL validation failed:\n";
    foreach ($result->getErrors() as $error) {
        echo "  ERROR: $error\n";
    }
    exit(1);
}

if ($result->hasWarnings()) {
    echo "  Warnings:\n";
    foreach ($result->getWarnings() as $warning) {
        echo "  - $warning\n";
    }
}

$importer = new DslImporter($graph);
$count = $importer->importFromFile(__DIR__ . '/dsl/rbac_rules.dsl');
echo "  ✓ Imported $count RBAC rules\n\n";

// Example 2: Import ownership rules
echo "2. Importing ownership rules...\n";
$count = $importer->importFromFile(__DIR__ . '/dsl/ownership_rules.dsl');
echo "  ✓ Imported $count ownership rules\n\n";

// Example 3: Import JSON policy
echo "3. Importing JSON policy file...\n";
$jsonValidator = new DslValidator();
$jsonContent = file_get_contents(__DIR__ . '/dsl/full_policy.json');
$jsonParser = new JsonDslParser();
$jsonResult = $jsonValidator->validate($jsonParser, $jsonContent);

if ($jsonResult->isValid()) {
    $count = $importer->import($jsonParser, $jsonContent);
    echo "  ✓ Imported $count rules from JSON\n\n";
} else {
    echo "  ✗ JSON validation failed\n";
    foreach ($jsonResult->getErrors() as $error) {
        echo "  ERROR: $error\n";
    }
}

// Example 4: Test permissions
echo "4. Testing permissions...\n";

// Test admin can create invoice
$hasPermission = $graph->hasPermission('role:admin', 'invoice', 'create');
echo "  - role:admin can create invoice: " . ($hasPermission ? '✓ YES' : '✗ NO') . "\n";

// Test accountant can view invoice
$hasPermission = $graph->hasPermission('role:accountant', 'invoice', 'view');
echo "  - role:accountant can view invoice: " . ($hasPermission ? '✓ YES' : '✗ NO') . "\n";

// Test accountant cannot delete invoice
$hasPermission = $graph->hasPermission('role:accountant', 'invoice', 'delete');
echo "  - role:accountant can delete invoice: " . ($hasPermission ? '✓ YES' : '✗ NO') . "\n";

// Test user with owner context
$hasPermission = $graph->hasPermission('user:123', 'invoice', 'edit', ['is_owner' => true]);
echo "  - user:123 can edit invoice (as owner): " . ($hasPermission ? '✓ YES' : '✗ NO') . "\n";

// Test user without owner context
$hasPermission = $graph->hasPermission('user:123', 'invoice', 'edit', ['is_owner' => false]);
echo "  - user:123 can edit invoice (not owner): " . ($hasPermission ? '✓ YES' : '✗ NO') . "\n";

// Test manager with department context
$hasPermission = $graph->hasPermission('role:manager', 'invoice', 'approve', ['department' => 'finance']);
echo "  - role:manager can approve invoice (finance dept): " . ($hasPermission ? '✓ YES' : '✗ NO') . "\n";

// Test manager with wrong department
$hasPermission = $graph->hasPermission('role:manager', 'invoice', 'approve', ['department' => 'engineering']);
echo "  - role:manager can approve invoice (engineering dept): " . ($hasPermission ? '✓ YES' : '✗ NO') . "\n";

echo "\n";

// Example 5: Export to different formats
echo "5. Exporting permissions...\n";
$exporter = new DslExporter($graph);

// Export to JSON
$jsonExport = $exporter->export('json');
echo "  - Exported to JSON format (" . strlen($jsonExport) . " bytes)\n";

// Export to line format
$lineExport = $exporter->export('line');
echo "  - Exported to line format (" . strlen($lineExport) . " bytes)\n";

// Save to files
$exporter->exportToFile('/tmp/authza_export.json', 'json');
$exporter->exportToFile('/tmp/authza_export.dsl', 'line');
echo "  ✓ Saved exports to /tmp/authza_export.{json,dsl}\n\n";

// Example 6: Show summary
echo "6. Summary:\n";
$allPermissions = $graph->getAllPermissions();
echo "  - Total permissions in graph: " . count($allPermissions) . "\n";

// Count unique subjects
$subjects = array_unique(array_column($allPermissions, 'subject'));
echo "  - Unique subjects: " . count($subjects) . "\n";

// Count unique resources
$resources = array_unique(array_column($allPermissions, 'resource'));
echo "  - Unique resources: " . count($resources) . "\n";

// Count rules with conditions
$withConditions = array_filter($allPermissions, fn($p) => $p['condition'] !== null);
echo "  - Rules with conditions: " . count($withConditions) . "\n";

echo "\n=== DSL Usage Example Complete ===\n";
