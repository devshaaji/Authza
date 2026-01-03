<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Authza\Core\Graph\PermissionGraph;
use Authza\Adapters\Cache\ArrayCache;

echo "=== Role Hierarchy Optimization Example ===\n";
echo "Demonstrates pre-collapsed role inheritance for improved authorization performance\n\n";

// Initialize with cache
$cache = new ArrayCache();
$graph = new PermissionGraph($cache);

// Set up permissions for various roles
$permissions = [
    // User permissions
    ['subjectId' => 'user:alice', 'action' => 'view', 'resourceType' => 'invoice', 'resourceId' => '*', 'allowed' => true],
    
    // Role permissions
    ['subjectId' => 'role:manager', 'action' => 'view', 'resourceType' => 'invoice', 'resourceId' => '*', 'allowed' => true],
    ['subjectId' => 'role:manager', 'action' => 'edit', 'resourceType' => 'invoice', 'resourceId' => '*', 'allowed' => true],
    ['subjectId' => 'role:manager', 'action' => 'approve', 'resourceType' => 'invoice', 'resourceId' => '*', 'allowed' => true],
    
    ['subjectId' => 'role:staff', 'action' => 'view', 'resourceType' => 'invoice', 'resourceId' => '*', 'allowed' => true],
    ['subjectId' => 'role:staff', 'action' => 'edit', 'resourceType' => 'invoice', 'resourceId' => '*', 'allowed' => true],
    
    ['subjectId' => 'role:junior', 'action' => 'view', 'resourceType' => 'invoice', 'resourceId' => '*', 'allowed' => true],
    ['subjectId' => 'role:junior', 'action' => 'edit', 'resourceType' => 'invoice', 'resourceId' => '*', 'allowed' => true],
    ['subjectId' => 'role:junior', 'action' => 'delete', 'resourceType' => 'invoice', 'resourceId' => '*', 'allowed' => false],
    
    ['subjectId' => 'role:finance', 'action' => 'view', 'resourceType' => 'invoice', 'resourceId' => '*', 'allowed' => true],
    ['subjectId' => 'role:finance', 'action' => 'approve', 'resourceType' => 'invoice', 'resourceId' => '*', 'allowed' => true],
    
    ['subjectId' => 'role:admin', 'action' => 'view', 'resourceType' => 'invoice', 'resourceId' => '*', 'allowed' => true],
    ['subjectId' => 'role:admin', 'action' => 'edit', 'resourceType' => 'invoice', 'resourceId' => '*', 'allowed' => true],
    ['subjectId' => 'role:admin', 'action' => 'delete', 'resourceType' => 'invoice', 'resourceId' => '*', 'allowed' => true],
    ['subjectId' => 'role:admin', 'action' => 'approve', 'resourceType' => 'invoice', 'resourceId' => '*', 'allowed' => true],
];

$graph->precompute($permissions);
echo "✓ Precomputed " . count($permissions) . " permission rules\n\n";

// Example 1: Without role hierarchy (traditional linear approach)
echo "Example 1: Authorization WITHOUT role hierarchy\n";
echo "────────────────────────────────────────────────\n";

$subjectIds = ['user:alice', 'role:manager', 'role:finance', 'role:staff'];
echo "Subjects: " . implode(', ', $subjectIds) . "\n";
echo "Check: Can these subjects view invoices?\n";

$canView = $graph->checkMultiple($subjectIds, 'view', 'invoice', '*');
echo "Result: " . ($canView ? '✓ YES' : ($canView === false ? '✗ NO' : '? NOT DEFINED')) . "\n";

echo "Check: Can these subjects approve invoices?\n";
$canApprove = $graph->checkMultiple($subjectIds, 'approve', 'invoice', '*');
echo "Result: " . ($canApprove ? '✓ YES' : ($canApprove === false ? '✗ NO' : '? NOT DEFINED')) . "\n";

echo "Check: Can these subjects delete invoices?\n";
$canDelete = $graph->checkMultiple($subjectIds, 'delete', 'invoice', '*');
echo "Result: " . ($canDelete ? '✓ YES' : ($canDelete === false ? '✗ NO' : '? NOT DEFINED')) . "\n\n";

// Example 2: With role hierarchy (optimized approach)
echo "Example 2: Authorization WITH pre-collapsed role hierarchy\n";
echo "───────────────────────────────────────────────────────────\n";

// Define organizational role hierarchy
// Alice is a manager with finance responsibilities
$hierarchy = [
    'user:alice' => ['role:manager', 'role:finance', 'role:staff'],
    'user:bob' => ['role:staff', 'role:junior'],
    'user:charlie' => ['role:admin'],
    'user:diana' => ['role:manager', 'role:staff'],
];

$graph->setRoleHierarchy($hierarchy);
$graph->flush();

echo "Organization hierarchy configured:\n";
foreach ($hierarchy as $user => $roles) {
    echo "  $user → " . implode(', ', $roles) . "\n";
}
echo "\n";

// Now when we check with just the user ID, the hierarchy is used
echo "Check: What can user:alice do?\n";
$effectiveSubjects = $graph->getEffectiveSubjects('user:alice');
echo "Effective subjects: " . implode(', ', $effectiveSubjects) . "\n";

echo "  - Can view invoices? " . ($graph->checkMultiple(['user:alice'], 'view', 'invoice', '*') ? '✓ YES' : '✗ NO') . "\n";
echo "  - Can edit invoices? " . ($graph->checkMultiple(['user:alice'], 'edit', 'invoice', '*') ? '✓ YES' : '✗ NO') . "\n";
echo "  - Can approve invoices? " . ($graph->checkMultiple(['user:alice'], 'approve', 'invoice', '*') ? '✓ YES' : '✗ NO') . "\n";
echo "  - Can delete invoices? " . ($graph->checkMultiple(['user:alice'], 'delete', 'invoice', '*') ? '✓ YES' : '✗ NO') . "\n";

echo "\nCheck: What can user:bob do?\n";
$effectiveSubjects = $graph->getEffectiveSubjects('user:bob');
echo "Effective subjects: " . implode(', ', $effectiveSubjects) . "\n";

echo "  - Can view invoices? " . ($graph->checkMultiple(['user:bob'], 'view', 'invoice', '*') ? '✓ YES' : '✗ NO') . "\n";
echo "  - Can edit invoices? " . ($graph->checkMultiple(['user:bob'], 'edit', 'invoice', '*') ? '✓ YES' : '✗ NO') . "\n";
echo "  - Can approve invoices? " . ($graph->checkMultiple(['user:bob'], 'approve', 'invoice', '*') ? '✓ YES' : '✗ NO') . "\n";
echo "  - Can delete invoices? " . ($graph->checkMultiple(['user:bob'], 'delete', 'invoice', '*') ? '✓ YES' : ($graph->checkMultiple(['user:bob'], 'delete', 'invoice', '*') === null ? '? NOT DEFINED' : '✗ NO')) . "\n";

echo "\nCheck: What can user:charlie (admin) do?\n";
$effectiveSubjects = $graph->getEffectiveSubjects('user:charlie');
echo "Effective subjects: " . implode(', ', $effectiveSubjects) . "\n";

echo "  - Can view invoices? " . ($graph->checkMultiple(['user:charlie'], 'view', 'invoice', '*') ? '✓ YES' : '✗ NO') . "\n";
echo "  - Can edit invoices? " . ($graph->checkMultiple(['user:charlie'], 'edit', 'invoice', '*') ? '✓ YES' : '✗ NO') . "\n";
echo "  - Can approve invoices? " . ($graph->checkMultiple(['user:charlie'], 'approve', 'invoice', '*') ? '✓ YES' : '✗ NO') . "\n";
echo "  - Can delete invoices? " . ($graph->checkMultiple(['user:charlie'], 'delete', 'invoice', '*') ? '✓ YES' : '✗ NO') . "\n\n";

// Example 3: Deny rules take precedence
echo "Example 3: Deny rules take precedence over allow rules\n";
echo "──────────────────────────────────────────────────────\n";

$graph2 = new PermissionGraph();

$permissions2 = [
    ['subjectId' => 'user:intern', 'action' => 'delete', 'resourceType' => 'invoice', 'resourceId' => '*', 'allowed' => true],
    ['subjectId' => 'role:junior', 'action' => 'delete', 'resourceType' => 'invoice', 'resourceId' => '*', 'allowed' => false],
];

$graph2->precompute($permissions2);

$hierarchy2 = [
    'user:intern' => ['role:junior'],
];

$graph2->setRoleHierarchy($hierarchy2);

echo "Permissions:\n";
echo "  user:intern → delete invoice: ALLOW\n";
echo "  role:junior → delete invoice: DENY\n\n";

echo "Role hierarchy:\n";
echo "  user:intern inherits role:junior\n\n";

$canDelete = $graph2->checkMultiple(['user:intern'], 'delete', 'invoice', '*');
echo "Result: user:intern can delete invoice? " . ($canDelete === false ? '✗ NO (deny wins)' : '✓ YES') . "\n\n";

// Example 4: Statistics
echo "Example 4: Graph Statistics\n";
echo "───────────────────────────\n";

$stats = $graph->getStats();
echo "Permission rules: " . $stats['total_rules'] . "\n";
echo "Role hierarchies configured: " . $stats['role_hierarchy_count'] . "\n";
echo "Rules by resource type:\n";
foreach ($stats['by_resource_type'] as $type => $count) {
    echo "  - $type: $count rules\n";
}
echo "\n";

// Example 5: Cache persistence
echo "Example 5: Cache Persistence\n";
echo "─────────────────────────────\n";

$cache3 = new ArrayCache();
$graph3 = new PermissionGraph($cache3);

$graph3->setRoleHierarchy([
    'user:john' => ['role:developer', 'role:staff'],
    'user:jane' => ['role:devops', 'role:staff'],
]);

$graph3->flush();
echo "✓ Role hierarchies cached\n";

// Create new graph with same cache
$graph4 = new PermissionGraph($cache3);
$effectiveSubjects = $graph4->getEffectiveSubjects('user:john');
echo "✓ Hierarchy loaded from cache: " . implode(', ', $effectiveSubjects) . "\n\n";

// Example 6: Incremental role addition
echo "Example 6: Incremental Role Addition\n";
echo "─────────────────────────────────────\n";

$graph5 = new PermissionGraph();

$graph5->addRoleInheritance('user:alice', 'role:senior_dev');
echo "Added user:alice → role:senior_dev\n";

$graph5->addRoleInheritance('user:alice', 'role:team_lead');
echo "Added user:alice → role:team_lead\n";

$graph5->addRoleInheritance('user:alice', ['role:architect', 'role:mentor']);
echo "Added user:alice → role:architect, role:mentor\n";

$effectiveSubjects = $graph5->getEffectiveSubjects('user:alice');
echo "Final effective subjects: " . implode(', ', $effectiveSubjects) . "\n\n";

echo "=== Role Hierarchy Optimization Example Complete ===\n";
echo "\nKey Benefits:\n";
echo "✓ Pre-collapse roles at initialization time\n";
echo "✓ checkMultiple() uses expanded hierarchy instead of iterating\n";
echo "✓ Hierarchies are cached and persisted\n";
echo "✓ Supports complex inheritance chains\n";
echo "✓ Deny rules always take precedence\n";
echo "✓ Perfect for multi-tenant and enterprise systems\n";
