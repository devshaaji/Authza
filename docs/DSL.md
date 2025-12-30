# DSL (Domain-Specific Language) Documentation

## Overview

The Authza DSL provides a human-readable way to define authorization policies without writing PHP code. This enables administrators, DevOps engineers, and non-PHP developers to manage permissions effectively.

## Table of Contents

- [Quick Start](#quick-start)
- [DSL Formats](#dsl-formats)
- [Subject Formats](#subject-formats)
- [Resource Formats](#resource-formats)
- [Actions](#actions)
- [Conditions](#conditions)
- [Import & Export](#import--export)
- [Validation](#validation)
- [Integration](#integration)
- [Best Practices](#best-practices)
- [Migration Guide](#migration-guide)

---

## Quick Start

### Basic Example

Create a file `permissions.dsl`:

```
# Admin has full access to invoices
role:admin, invoice, create
role:admin, invoice, edit
role:admin, invoice, delete
role:admin, invoice, view
```

Import it:

```php
use Authza\Core\Graph\PermissionGraph;
use Authza\DSL\DslImporter;
use Authza\Adapters\Cache\ArrayCache;

$graph = new PermissionGraph(new ArrayCache());
$importer = new DslImporter($graph);

$count = $importer->importFromFile('permissions.dsl');
echo "Imported {$count} rules\n";
```

---

## DSL Formats

### Line-Based Format

Simple, readable format with one rule per line.

**Syntax:**
```
subject, resource, action[, condition]
```

**Example:**
```
role:admin, invoice, create
role:accountant, invoice, view
user:42, invoice:123, delete, owner
```

**Features:**
- Comments: Lines starting with `#`
- Empty lines are ignored
- Whitespace is automatically trimmed
- Condition is optional

### JSON Format

Structured format ideal for programmatic generation and API integration.

**Syntax:**
```json
[
  {
    "subject": "role:admin",
    "resource": "invoice",
    "action": "create",
    "condition": "optional"
  }
]
```

**Example:**
```json
[
  {
    "subject": "role:admin",
    "resource": "invoice",
    "action": "create"
  },
  {
    "subject": "user:42",
    "resource": "invoice:123",
    "action": "delete",
    "condition": "owner"
  }
]
```

---

## Subject Formats

Subjects identify **who** is requesting access.

### Role-Based Subject

Format: `role:ROLE_NAME`

**Examples:**
```
role:admin, invoice, create
role:accountant, invoice, view
role:manager, invoice, approve
```

### User-Based Subject

Format: `user:USER_ID`

**Examples:**
```
user:42, invoice:123, edit
user:999, client:456, view
```

### Wildcard Subject

Format: `user:*` or `role:*`

Matches any user or role of that type.

**Examples:**
```
# Any user can view their own invoices
user:*, invoice, view, owner

# Any user can edit their own profile
user:*, user, edit, owner
```

---

## Resource Formats

Resources identify **what** is being accessed.

### Generic Resource

Format: `RESOURCE_TYPE`

Applies to all instances of a resource type.

**Examples:**
```
role:admin, invoice, create
role:accountant, client, view
```

### Specific Resource Instance

Format: `RESOURCE_TYPE:RESOURCE_ID`

Applies to a specific resource instance.

**Examples:**
```
user:42, invoice:123, delete
user:99, client:456, edit
```

### Wildcard Resource

Format: `*` or `RESOURCE_TYPE:*`

**Examples:**
```
# Super admin can do anything
role:superadmin, *, *

# Manager can view all invoices
role:manager, invoice:*, view
```

---

## Actions

Actions define **what operation** is being performed.

### Standard Actions

Common actions across most applications:

- `create` - Create new resources
- `read` / `view` - Read/view resources
- `edit` / `update` - Modify existing resources
- `delete` - Remove resources
- `approve` - Approve requests or documents
- `reject` - Reject requests or documents

**Examples:**
```
role:admin, invoice, create
role:accountant, invoice, view
role:manager, invoice, approve
```

### Custom Actions

You can define custom actions for your application:

```
role:auditor, invoice, audit
role:accountant, invoice, export
role:manager, invoice, finalize
```

---

## Conditions

Conditions add **context-aware** authorization rules.

### Owner Condition

Format: `owner`

Grants permission only if the user owns the resource.

**Examples:**
```
# Users can edit their own invoices
user:*, invoice, edit, owner

# Users can view their own profile
user:*, user, view, owner
```

**Context Required:**
```php
$context = ['is_owner' => true];
$graph->hasPermission('user:123', 'invoice', 'edit', $context);
```

### Equality Condition

Format: `KEY==VALUE`

Grants permission when context key equals value.

**Examples:**
```
# Managers can approve invoices in their department
role:manager, invoice, approve, department==finance

# Regional managers can access specific regions
role:regional_manager, report, view, region==west
```

**Context Required:**
```php
$context = ['department' => 'finance'];
$graph->hasPermission('role:manager', 'invoice', 'approve', $context);
```

### Inequality Condition

Format: `KEY!=VALUE`

Grants permission when context key does NOT equal value.

**Examples:**
```
# Accountants can only edit unpaid invoices
role:accountant, invoice, edit, status!=paid

# Editors cannot modify published articles
role:editor, article, edit, status!=published
```

**Context Required:**
```php
$context = ['status' => 'draft'];
$graph->hasPermission('role:accountant', 'invoice', 'edit', $context);
```

---

## Import & Export

### Importing DSL Rules

#### From String

```php
use Authza\DSL\LineDslParser;
use Authza\DSL\JsonDslParser;
use Authza\DSL\DslImporter;

// Line-based DSL
$dsl = "role:admin, invoice, create\nrole:accountant, invoice, view";
$parser = new LineDslParser();
$count = $importer->import($parser, $dsl);

// JSON DSL
$json = '[{"subject":"role:admin","resource":"invoice","action":"create"}]';
$parser = new JsonDslParser();
$count = $importer->import($parser, $json);
```

#### From File

```php
// Auto-detects format by extension
$count = $importer->importFromFile('policies.dsl');  // Line format
$count = $importer->importFromFile('policies.json'); // JSON format
$count = $importer->importFromFile('policies.txt');  // Line format
```

### Exporting Permissions

#### To String

```php
use Authza\DSL\DslExporter;

$exporter = new DslExporter($graph);

// Export to JSON
$json = $exporter->export('json');

// Export to line format
$lines = $exporter->export('line');
```

#### To File

```php
// Export to JSON file
$exporter->exportToFile('backup.json', 'json');

// Export to line-based file
$exporter->exportToFile('backup.dsl', 'line');
```

---

## Validation

### Validating DSL Before Import

Always validate DSL content before importing to catch errors early.

```php
use Authza\DSL\DslValidator;

$validator = new DslValidator();
$result = $validator->validate($parser, $dslContent);

if (!$result->isValid()) {
    echo "Validation failed:\n";
    foreach ($result->getErrors() as $error) {
        echo "  ERROR: {$error}\n";
    }
    exit(1);
}

if ($result->hasWarnings()) {
    echo "Warnings:\n";
    foreach ($result->getWarnings() as $warning) {
        echo "  WARNING: {$warning}\n";
    }
}

// Safe to import
$importer->import($parser, $dslContent);
```

### Validation Checks

The validator checks for:

1. **Syntax Errors** - Malformed DSL
2. **Invalid Subjects** - Must be `role:*` or `user:*`
3. **Missing Required Fields** - Subject, resource, action required
4. **Duplicate Rules** - Same rule defined multiple times
5. **Conflicting Rules** - Wildcard vs specific rules
6. **Non-standard Resources/Actions** - Warnings for uncommon values

---

## Integration

### Integration with PermissionGraph

DSL rules are imported into the `PermissionGraph` for fast permission lookups.

```php
use Authza\Core\Graph\PermissionGraph;
use Authza\DSL\DslImporter;

$graph = new PermissionGraph($cache);
$importer = new DslImporter($graph);

// Import multiple DSL files
$importer->importFromFile('rbac_rules.dsl');
$importer->importFromFile('ownership_rules.dsl');
$importer->importFromFile('context_rules.dsl');

// Check permissions
if ($graph->hasPermission('role:admin', 'invoice', 'create')) {
    // Grant access
}
```

### Mixing DSL and Class-Based Policies

DSL rules and PHP policies can coexist:

```php
// Import DSL rules
$importer->importFromFile('basic_permissions.dsl');

// Add programmatic rules
$graph->addPermission('role:custom', 'resource', 'action', null);

// Both are checked during authorization
```

### Caching

The `PermissionGraph` supports PSR-16 caching:

```php
use Authza\Adapters\Cache\ArrayCache;

$cache = new ArrayCache();
$graph = new PermissionGraph($cache);

// Permissions are cached automatically
$importer->importFromFile('policies.dsl');
```

---

## Best Practices

### 1. Start Simple

Begin with basic RBAC rules:

```
role:admin, invoice, create
role:admin, invoice, edit
role:admin, invoice, delete
role:admin, invoice, view
```

### 2. Use Comments Liberally

```
# === Admin Permissions ===
role:admin, invoice, create
role:admin, invoice, edit

# === Accountant Permissions ===
role:accountant, invoice, view
role:accountant, invoice, edit, status!=paid
```

### 3. Group Related Rules

Organize rules by role, resource, or purpose:

```
rbac_rules.dsl       # Basic RBAC permissions
ownership_rules.dsl  # Ownership-based rules
context_rules.dsl    # Context-aware rules
```

### 4. Validate Before Deployment

```bash
# Validate DSL before deploying
php validate_policies.php policies.dsl
```

### 5. Version Control

Store DSL files in Git for:
- Change tracking
- Rollback capability
- Audit trail
- Collaboration

### 6. Use Specific Rules When Possible

```
# Prefer specific rules
role:accountant, invoice, view

# Over wildcards when possible
user:*, *, view
```

### 7. Document Complex Conditions

```
# Manager can approve invoices in their department
# Requires: context['department'] = user's department
role:manager, invoice, approve, department==finance
```

### 8. Regular Audits

Periodically review and validate permissions:

```php
$exporter = new DslExporter($graph);
$backup = $exporter->export('json');
// Review and audit the backup
```

---

## Migration Guide

### From Class-Based Policies to DSL

#### Before (PHP Policy Class)

```php
class InvoicePolicy
{
    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }
    
    public function view(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasRole('accountant');
    }
}
```

#### After (DSL)

```
# invoice_policy.dsl
role:admin, invoice, create
role:admin, invoice, view
role:accountant, invoice, view
```

### Migrating Complex Policies

#### Before

```php
public function edit(User $user, Invoice $invoice): bool
{
    // Admins can edit all
    if ($user->hasRole('admin')) {
        return true;
    }
    
    // Accountants can edit unpaid invoices
    if ($user->hasRole('accountant') && $invoice->status !== 'paid') {
        return true;
    }
    
    // Users can edit their own
    return $invoice->user_id === $user->id;
}
```

#### After

```
# Admin can edit all invoices
role:admin, invoice, edit

# Accountants can edit unpaid invoices
role:accountant, invoice, edit, status!=paid

# Users can edit their own invoices
user:*, invoice, edit, owner
```

### Migration Steps

1. **Audit existing policies** - List all current permissions
2. **Convert to DSL format** - Transform PHP logic to DSL rules
3. **Validate DSL** - Use `DslValidator` to check syntax
4. **Test in staging** - Verify behavior matches original
5. **Gradual rollout** - Migrate one resource at a time
6. **Monitor** - Check logs for authorization issues
7. **Decommission old policies** - Remove PHP policy classes

### Handling Edge Cases

Some complex logic may require PHP:

```php
// Complex business logic
if ($user->subscription->isActive() && 
    $user->accountAge() > 30 &&
    !$resource->hasRestriction('geographic')) {
    return true;
}
```

**Solution:** Keep complex policies in PHP, use DSL for simple rules.

---

## Examples

### Complete RBAC System

```
# === Administrator ===
role:admin, invoice, create
role:admin, invoice, edit
role:admin, invoice, delete
role:admin, invoice, view
role:admin, client, create
role:admin, client, edit
role:admin, client, delete
role:admin, client, view

# === Accountant ===
role:accountant, invoice, view
role:accountant, invoice, edit
role:accountant, invoice, create
role:accountant, client, view

# === Sales ===
role:sales, client, create
role:sales, client, edit
role:sales, client, view
role:sales, invoice, view

# === Manager ===
role:manager, invoice, approve
role:manager, invoice, view
role:manager, client, view
```

### Ownership-Based Rules

```
# Users can manage their own resources
user:*, invoice, view, owner
user:*, invoice, edit, owner
user:*, invoice, delete, owner
user:*, client, view, owner
user:*, client, edit, owner
```

### Context-Aware Rules

```
# Department-specific access
role:manager, invoice, approve, department==finance
role:manager, invoice, approve, department==sales
role:manager, report, view, department==finance

# Status-based access
role:accountant, invoice, edit, status!=paid
role:accountant, invoice, edit, status!=approved
role:editor, article, edit, status!=published

# Time-based (with custom context)
role:support, ticket, edit, business_hours==true
```

---

## Troubleshooting

### Common Errors

**Error: "Invalid subject format"**
```
# Wrong
admin, invoice, create

# Correct
role:admin, invoice, create
```

**Error: "Missing required field"**
```
# Wrong - Missing action
role:admin, invoice

# Correct
role:admin, invoice, create
```

**Error: "Unsupported file format"**
```php
// Wrong extension
$importer->importFromFile('policies.yaml');

// Correct - Use .dsl, .txt, or .json
$importer->importFromFile('policies.dsl');
```

### Debug Tips

1. **Enable validation:**
```php
$validator = new DslValidator();
$result = $validator->validate($parser, $content);
var_dump($result->getErrors());
```

2. **Check parsed rules:**
```php
$rules = $parser->parse($content);
var_dump($rules);
```

3. **Verify import:**
```php
$count = $importer->import($parser, $content);
echo "Imported {$count} rules\n";
var_dump($graph->getAllPermissions());
```

---

## API Reference

### DslParserInterface

```php
interface DslParserInterface
{
    public function parse(string $content): array;
}
```

### DslImporter

```php
class DslImporter
{
    public function __construct(PermissionGraph $graph);
    public function import(DslParserInterface $parser, string $content): int;
    public function importFromFile(string $filePath): int;
}
```

### DslExporter

```php
class DslExporter
{
    public function __construct(PermissionGraph $graph);
    public function export(string $format = 'json'): string;
    public function exportToFile(string $filePath, string $format): bool;
}
```

### DslValidator

```php
class DslValidator
{
    public function validate(DslParserInterface $parser, string $content): ValidationResult;
}
```

### ValidationResult

```php
class ValidationResult
{
    public function isValid(): bool;
    public function getErrors(): array;
    public function getWarnings(): array;
    public function hasWarnings(): bool;
    public function getErrorCount(): int;
    public function getWarningCount(): int;
}
```

---

## Conclusion

The Authza DSL provides a powerful, flexible way to define authorization policies that:

- ✅ Non-developers can understand and maintain
- ✅ Supports complex authorization scenarios
- ✅ Integrates seamlessly with PHP code
- ✅ Can be version controlled and audited
- ✅ Scales to large permission sets

For questions or contributions, please see the main project README.
