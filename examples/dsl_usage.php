<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Authza\Core\Graph\PermissionGraph;
use Authza\Core\Authorization;
use Authza\DSL\LineDslParser;
use Authza\DSL\JsonDslParser;
use Authza\DSL\DslImporter;
use Authza\DSL\DslValidator;
use Authza\Adapters\Cache\ArrayCache;

echo "=== Authza DSL Usage Example (Integrated with Authorization Engine) ===\n\n";

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

// Example 2: Import JSON policy
echo "2. Importing JSON policy file...\n";
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

// Example 3: Test permissions using main's Authorization Engine API
echo "3. Testing permissions using PermissionGraph...\n";

// DSL rule: "role:admin, invoice, create" becomes check('admin', 'create', 'invoice', '*')
$hasPermission = $graph->check('admin', 'create', 'invoice', '*');
echo "  - admin can create invoice: " . ($hasPermission ? '✓ YES' : '✗ NO') . "\n";

// DSL rule: "role:accountant, invoice, view" becomes check('accountant', 'view', 'invoice', '*')
$hasPermission = $graph->check('accountant', 'view', 'invoice', '*');
echo "  - accountant can view invoice: " . ($hasPermission ? '✓ YES' : '✗ NO') . "\n";

// Test permission that doesn't exist (returns null)
$hasPermission = $graph->check('accountant', 'delete', 'invoice', '*');
echo "  - accountant can delete invoice: " . ($hasPermission === null ? '✗ NOT DEFINED' : ($hasPermission ? '✓ YES' : '✗ NO')) . "\n";

// DSL rule with specific ID: "user:42, invoice:123, delete"
$hasPermission = $graph->check('42', 'delete', 'invoice', '123');
echo "  - user:42 can delete invoice:123: " . ($hasPermission ? '✓ YES' : ($hasPermission === null ? '✗ NOT DEFINED' : '✗ NO')) . "\n";

echo "\n";

// Example 4: Integration with Authorization Engine
echo "4. Using with Authorization Engine...\n";
echo "  Note: DSL provides policy input format\n";
echo "  The Authorization Engine uses these precomputed permissions\n";
echo "  for fast authorization decisions.\n\n";

// Example 5: Summary
echo "5. Summary:\n";
echo "  - DSL provides human-readable policy format\n";
echo "  - Validators ensure policy correctness before import\n";
echo "  - Importers convert DSL to PermissionGraph format\n";
echo "  - PermissionGraph integrates with Authorization Engine\n";
echo "  - Both line-based (.dsl) and JSON formats supported\n\n";

echo "=== DSL Usage Example Complete ===\n";
echo "\nNext steps:\n";
echo "  1. Define policies in DSL format (.dsl or .json files)\n";
echo "  2. Validate policies with DslValidator\n";
echo "  3. Import policies with DslImporter\n";
echo "  4. Use Authorization Engine for authorization decisions\n";
