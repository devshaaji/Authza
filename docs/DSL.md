# Authza DSL Documentation

Complete guide to the Authza Domain-Specific Language for defining authorization policies.

---

## Table of Contents

- [Overview](#overview)
- [Quick Start](#quick-start)
- [DSL Formats](#dsl-formats)
- [Policy Structure](#policy-structure)
- [Subject Patterns](#subject-patterns)
- [Resource Patterns](#resource-patterns)
- [Actions](#actions)
- [Effects (Allow/Deny)](#effects-allowdeny)
- [Conditions](#conditions)
- [PolicyDefinition DTO](#policydefinition-dto)
- [Policy Sources](#policy-sources)
- [Import & Export](#import--export)
- [Validation](#validation)
- [Integration with Authorization Engine](#integration-with-authorization-engine)
- [Best Practices](#best-practices)
- [Migration Guide](#migration-guide)
- [Examples](#examples)
- [API Reference](#api-reference)

---

## Overview

The Authza DSL provides a human-readable way to define authorization policies without writing PHP code. This enables:

- **Administrators** to manage permissions without developer involvement
- **DevOps engineers** to deploy policy changes via CI/CD
- **Non-PHP developers** to integrate with the authorization system
- **Auditors** to review and understand access controls

### Key Features

- ✅ Two formats: JSON and line-based
- ✅ Allow/Deny rules with conflict resolution
- ✅ Conditional rules (ownership, attributes)
- ✅ Wildcard patterns for subjects and resources
- ✅ Validation with detailed error reporting
- ✅ Import/Export capabilities
- ✅ CLI tools for management

---

## Quick Start

### 1. Create a Policy File

**JSON format (`policies.json`):**
```json
[
  {"subject": "role:admin", "resource": "invoice", "action": "create", "effect": "allow"},
  {"subject": "role:admin", "resource": "invoice", "action": "edit", "effect": "allow"},
  {"subject": "role:accountant", "resource": "invoice", "action": "view", "effect": "allow"},
  {"subject": "user:*", "resource": "invoice", "action": "view", "condition": "owner", "effect": "allow"}
]
```

**Line format (`policies.dsl`):**
```
role:admin, invoice, create
role:admin, invoice, edit
role:accountant, invoice, view
user:*, invoice, view, owner
```

### 2. Import via CLI

```bash
# Validate first
./vendor/bin/authz validate --file=policies.json --strict

# Import
./vendor/bin/authz import --file=policies.json

# Build permission graph
./vendor/bin/authz graph:build
```

### 3. Import via PHP

```php
<?php
use Authza\Core\Graph\PermissionGraph;
use Authza\DSL\DslImporter;
use Authza\DSL\JsonDslParser;
use Authza\Adapters\Cache\ArrayCache;

$graph = new PermissionGraph(new ArrayCache());
$importer = new DslImporter($graph);

$count = $importer->importFromFile('policies.json');
echo "Imported {$count} rules\n";
```

---

## DSL Formats

### JSON Format

Structured format ideal for programmatic generation, APIs, and complex policies.

```json
[
  {
    "subject": "role:admin",
    "resource": "invoice",
    "action": "create",
    "effect": "allow"
  },
  {
    "subject": "role:accountant",
    "resource": "invoice",
    "action": "edit",
    "condition": "status!=paid",
    "effect": "allow"
  },
  {
    "subject": "user:*",
    "resource": "invoice",
    "action": "delete",
    "condition": "owner",
    "effect": "allow"
  },
  {
    "subject": "role:intern",
    "resource": "invoice",
    "action": "delete",
    "effect": "deny"
  }
]
```

### Line-Based Format

Simple, readable format with one rule per line. Great for manual editing.

**Syntax:**
```
subject, resource, action[, condition[, effect]]
```

**Example:**
```
# Comments start with #
# Empty lines are ignored

# === Administrator Rules ===
role:admin, invoice, create
role:admin, invoice, edit
role:admin, invoice, delete
role:admin, invoice, view

# === Accountant Rules ===
role:accountant, invoice, view
role:accountant, invoice, edit, status!=paid, allow

# === Ownership Rules ===
user:*, invoice, view, owner
user:*, invoice, edit, owner
user:*, invoice, delete, owner, allow

# === Deny Rules ===
role:intern, invoice, delete, , deny
```

**Features:**
- Comments: Lines starting with `#`
- Empty lines are ignored
- Whitespace is automatically trimmed
- Condition and effect are optional
- Use empty condition with `, ,` syntax when specifying effect only

---

## Policy Structure

Each policy rule consists of five components:

| Field | Required | Default | Description |
|-------|----------|---------|-------------|
| `subject` | ✅ Yes | - | Who is requesting access |
| `resource` | ✅ Yes | - | What is being accessed |
| `action` | ✅ Yes | - | Operation being performed |
| `condition` | ❌ No | `null` | Context-aware condition |
| `effect` | ❌ No | `allow` | Whether to allow or deny |

### PolicyDefinition DTO

All DSL rules are converted to `PolicyDefinition` objects internally:

```php
<?php
namespace Authza\DSL;

final class PolicyDefinition
{
    public string $subject;    // role:admin | user:42 | user:*
    public string $resource;   // invoice | invoice:123 | invoice:*
    public string $action;     // create | edit | delete | view
    public ?string $condition; // owner | department==finance | null
    public string $effect;     // allow | deny
}
```

---

## Subject Patterns

Subjects identify **who** is requesting access.

> **Important Security Note:** Subject identifiers preserve their type prefix (`role:` or `user:`) internally to prevent authorization bypass. This ensures that a user with ID `developer` cannot accidentally gain permissions meant for `role:developer`. The system stores and checks permissions using the full prefixed identifier (e.g., `role:developer:create:invoice:*`).

### Role-Based Subject

Format: `role:ROLE_NAME`

```
role:admin, invoice, create
role:accountant, invoice, view
role:manager, invoice, approve
role:super_admin, *, *
```

### User-Based Subject

Format: `user:USER_ID`

```
user:42, invoice:123, edit
user:999, client:456, view
user:1, *, *, , allow
```

### Wildcard Subjects

| Pattern | Description |
|---------|-------------|
| `user:*` | Any authenticated user |
| `role:*` | Any user with at least one role |

```
# Any user can view their own invoices
user:*, invoice, view, owner

# Any user can edit their own profile
user:*, user, edit, owner

# Any role can view public resources
role:*, public, view
```

---

## Resource Patterns

Resources identify **what** is being accessed.

### Type-Level Resource

Format: `RESOURCE_TYPE`

Applies to all instances of a resource type.

```
role:admin, invoice, create
role:accountant, client, view
role:manager, report, generate
```

### Instance-Level Resource

Format: `RESOURCE_TYPE:RESOURCE_ID`

Applies to a specific resource instance.

```
user:42, invoice:123, delete
user:99, client:456, edit
role:admin, system:config, edit
```

### Wildcard Resources

| Pattern | Description |
|---------|-------------|
| `invoice:*` | Any invoice instance |
| `*` | Any resource (use carefully) |

```
# Manager can view all invoice instances
role:manager, invoice:*, view

# Super admin can do anything (dangerous!)
role:superadmin, *, *
```

---

## Actions

Actions define **what operation** is being performed.

### Standard Actions

| Action | Description |
|--------|-------------|
| `create` | Create new resources |
| `read` | Read resource data |
| `view` | View/display resources |
| `edit` | Modify existing resources |
| `update` | Update resources (alias for edit) |
| `delete` | Remove resources |
| `approve` | Approve requests/documents |
| `reject` | Reject requests/documents |

### Custom Actions

Define any action your application needs:

```
role:auditor, invoice, audit
role:accountant, invoice, export
role:manager, invoice, finalize
role:support, ticket, escalate
role:editor, article, publish
```

### Wildcard Action

```
# Admin can perform any action on invoices
role:admin, invoice, *
```

---

## Effects (Allow/Deny)

The `effect` field determines whether a matching rule grants or denies access.

### Allow (Default)

```json
{"subject": "role:admin", "resource": "invoice", "action": "create", "effect": "allow"}
```

```
role:admin, invoice, create, , allow
role:admin, invoice, create              # effect defaults to "allow"
```

### Deny

Deny rules explicitly block access, even if other rules would allow it.

```json
{"subject": "role:admin", "resource": "invoice:999", "action": "delete", "effect": "deny"}
```

```
role:admin, invoice:999, delete, , deny
role:intern, invoice, delete, , deny
```

### Conflict Resolution

When multiple rules match, the following precedence applies:

1. **Deny takes precedence** - Any matching deny rule blocks access
2. **Specific over general** - `invoice:123` beats `invoice`
3. **User over role** - `user:42` beats `role:admin`

**Example:**
```json
[
  {"subject": "role:admin", "resource": "invoice", "action": "*", "effect": "allow"},
  {"subject": "role:admin", "resource": "invoice:999", "action": "delete", "effect": "deny"}
]
```
Result: Admins can do anything with invoices EXCEPT delete invoice #999.

---

## Conditions

Conditions add **context-aware** authorization rules.

### Owner Condition

Grants permission only if the user owns the resource.

```
user:*, invoice, edit, owner
user:*, invoice, delete, owner
user:*, profile, edit, owner
```

**Usage:**
```php
$context = ['is_owner' => ($invoice->user_id === $user->id)];
$authz->can($user, 'edit', $invoice, $context);
```

### Equality Condition

Format: `KEY==VALUE`

```
role:manager, invoice, approve, department==finance
role:regional, report, view, region==west
role:support, ticket, edit, priority==high
```

**Usage:**
```php
$context = ['department' => $user->department];
$authz->can($user, 'approve', $invoice, $context);
```

### Inequality Condition

Format: `KEY!=VALUE`

```
role:accountant, invoice, edit, status!=paid
role:editor, article, edit, status!=published
role:support, ticket, close, status!=escalated
```

**Usage:**
```php
$context = ['status' => $invoice->status];
$authz->can($user, 'edit', $invoice, $context);
```

### Custom Conditions

Implement `ConditionResolverInterface` for complex conditions:

```php
<?php
use Authza\Condition\ConditionResolverInterface;

class TimeConditionResolver implements ConditionResolverInterface
{
    public function supports(string $condition): bool
    {
        return str_starts_with($condition, 'time.');
    }

    public function evaluate(
        string $condition,
        SubjectInterface $user,
        ResourceInterface $resource,
        array $context = []
    ): bool {
        // Parse: time.between(09:00,17:00)
        if (preg_match('/^time\.between\((\d{2}:\d{2}),(\d{2}:\d{2})\)$/', $condition, $matches)) {
            $now = date('H:i');
            return $now >= $matches[1] && $now <= $matches[2];
        }
        return false;
    }
}
```

**DSL usage:**
```
role:support, ticket, edit, time.between(09:00,17:00)
```

---

## PolicyDefinition DTO

The `PolicyDefinition` class is the canonical format for all DSL policies.

### Creating PolicyDefinitions

```php
<?php
use Authza\DSL\PolicyDefinition;

// Via constructor
$policy = new PolicyDefinition(
    subject: 'role:admin',
    resource: 'invoice',
    action: 'create',
    condition: null,
    effect: 'allow'
);

// From array
$policy = PolicyDefinition::fromArray([
    'subject' => 'role:admin',
    'resource' => 'invoice',
    'action' => 'create',
    'condition' => null,
    'effect' => 'allow'
]);

// To array
$array = $policy->toArray();
// ['subject' => 'role:admin', 'resource' => 'invoice', 'action' => 'create', 'effect' => 'allow']
```

### Validation

The constructor validates the `effect` field:

```php
// Throws InvalidArgumentException
$policy = new PolicyDefinition('role:admin', 'invoice', 'create', null, 'granted');
// Error: Effect must be 'allow' or 'deny', got: granted
```

---

## Policy Sources

Use `PolicySourceInterface` to load policies from various sources.

### PolicySourceInterface

```php
<?php
namespace Authza\DSL;

interface PolicySourceInterface
{
    /**
     * @return array<PolicyDefinition>
     */
    public function load(): array;
}
```

### DslPolicySource

Load policies from DSL files:

```php
<?php
use Authza\DSL\DslPolicySource;
use Authza\DSL\JsonDslParser;
use Authza\DSL\LineDslParser;
use Authza\DSL\DslValidator;

// From JSON file
$source = new DslPolicySource(
    parser: new JsonDslParser(),
    validator: new DslValidator(),  // Optional
    filePath: 'policies.json'
);
$policies = $source->load();

// From line-based file
$source = new DslPolicySource(
    parser: new LineDslParser(),
    filePath: 'policies.dsl'
);
$policies = $source->load();

// From string content
$source = new DslPolicySource(
    parser: new JsonDslParser(),
    content: '[{"subject":"role:admin","resource":"invoice","action":"create"}]'
);
$policies = $source->load();
```

### Using with PolicyRegistry

Policies are loaded immediately when you call `registerSource()`:

```php
<?php
use Authza\Core\PolicyRegistry;
use Authza\DSL\DslPolicySource;
use Authza\DSL\JsonDslParser;
use Authza\DSL\LineDslParser;

$registry = new PolicyRegistry();

// Register PHP-based policies
$registry->register('invoice', new InvoicePolicy());
$registry->register('user', new UserPolicy());

// Register DSL sources - policies are loaded immediately!
$registry->registerSource(new DslPolicySource(
    new JsonDslParser(),
    filePath: 'rbac_rules.json'
));

$registry->registerSource(new DslPolicySource(
    new LineDslParser(),
    filePath: 'ownership_rules.dsl'
));

// Policies are already available - no need to call loadAll()
$dslPolicies = $registry->getDslPolicies();

// If you need to reload all sources (e.g., after file changes)
$registry->reloadAll();
```

---

## Import & Export

### Importing DSL Rules

#### Via DslImporter

```php
<?php
use Authza\DSL\DslImporter;
use Authza\DSL\JsonDslParser;
use Authza\DSL\LineDslParser;

$importer = new DslImporter($graph);

// From string
$json = '[{"subject":"role:admin","resource":"invoice","action":"create","effect":"allow"}]';
$count = $importer->import(new JsonDslParser(), $json);

// From file (auto-detects format)
$count = $importer->importFromFile('policies.json');  // JSON
$count = $importer->importFromFile('policies.dsl');   // Line format
$count = $importer->importFromFile('policies.txt');   // Line format
```

#### Via DslPolicySource

```php
<?php
use Authza\DSL\DslPolicySource;
use Authza\DSL\JsonDslParser;

$source = new DslPolicySource(
    parser: new JsonDslParser(),
    filePath: 'policies.json'
);

// Get PolicyDefinition objects
$policies = $source->load();

// Add to graph
foreach ($policies as $policy) {
    $graph->addRule($policy->toArray());
}
```

### Exporting Policies

```php
<?php
use Authza\DSL\DslExporter;

$exporter = new DslExporter($policies);  // Array of PolicyDefinition

// Export to JSON string
$json = $exporter->export('json');

// Export to line format string
$lines = $exporter->export('line');

// Export includes effect field
// JSON: {"subject":"role:admin","resource":"invoice","action":"create","effect":"allow"}
// Line: role:admin, invoice, create, , allow
```

---

## Validation

### Using DslValidator

```php
<?php
use Authza\DSL\DslValidator;
use Authza\DSL\JsonDslParser;

$validator = new DslValidator();
$parser = new JsonDslParser();
$result = $validator->validate($parser, $jsonContent);

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

echo "Validation passed!\n";
```

### Validation Checks

| Check | Type | Description |
|-------|------|-------------|
| Syntax errors | Error | Malformed JSON/DSL |
| Invalid subject | Error | Must be `role:*` or `user:*` |
| Missing fields | Error | subject, resource, action required |
| Invalid effect | Error | Must be `allow` or `deny` |
| Empty identifier | Error | `role:` or `user:` without value |
| Duplicate rules | Warning | Same rule defined twice |
| Conflicting rules | Warning | Wildcard vs specific conflicts |
| Non-standard resource | Warning | Unknown resource type |
| Non-standard action | Warning | Unknown action |

### ValidationResult API

```php
$result->isValid();         // bool - no errors
$result->hasWarnings();     // bool - has warnings
$result->getErrors();       // array<string>
$result->getWarnings();     // array<string>
$result->getErrorCount();   // int
$result->getWarningCount(); // int
```

---

## Integration with Authorization Engine

### Building the Permission Graph

```php
<?php
use Authza\Core\Graph\PermissionGraph;
use Authza\Core\Authorization;
use Authza\Core\PolicyRegistry;
use Authza\DSL\DslPolicySource;
use Authza\DSL\JsonDslParser;
use Authza\Adapters\Cache\ArrayCache;

// Create components
$cache = new ArrayCache();
$graph = new PermissionGraph($cache);
$registry = new PolicyRegistry();

// Register DSL source
$registry->registerSource(new DslPolicySource(
    new JsonDslParser(),
    filePath: 'policies.json'
));

// Load and build graph
$policies = $registry->loadAll();
foreach ($policies as $policy) {
    $graph->addRule($policy->toArray());
}

// Create authorization instance
$authz = new Authorization($registry, $cache, $logger, $graph);

// Check permissions
if ($authz->can($user, 'edit', $invoice, $context)) {
    // Allowed
}
```

### Complete Flow

```
DSL File (JSON/Line)
        ↓
   DSL Parser (JsonDslParser / LineDslParser)
        ↓
   DslPolicySource
        ↓
   PolicyDefinition[] (canonical format)
        ↓
   PolicyRegistry::registerSource()
        ↓
   PermissionGraph::addRule()
        ↓
   Authorization::can() / authorize()
        ↓
   Result (allow/deny)
```

---

## Best Practices

### 1. Use Explicit Effects

```
# Good - clear intent
role:admin, invoice, create, , allow
role:intern, invoice, delete, , deny

# Okay - defaults to allow
role:admin, invoice, create
```

### 2. Organize by Purpose

```
policies/
├── rbac_rules.json       # Role-based rules
├── ownership_rules.dsl   # Ownership conditions
├── deny_rules.json       # Explicit denials
└── context_rules.dsl     # Context-aware rules
```

### 3. Use Comments

```
# === Finance Department ===
# Accountants can view all invoices
role:accountant, invoice, view

# Accountants can edit unpaid invoices only
role:accountant, invoice, edit, status!=paid

# === Restrictions ===
# Interns cannot delete anything
role:intern, invoice, delete, , deny
role:intern, client, delete, , deny
```

### 4. Validate Before Deploying

```bash
# Always validate with strict mode in CI
authz validate --file=policies.json --strict

# Dry-run before importing
authz import --file=policies.json --dry-run
```

### 5. Version Control Policies

```bash
git add policies/
git commit -m "Add accountant invoice permissions"
git push
```

### 6. Use Deny Rules Sparingly

```
# Prefer: specific allow rules
role:accountant, invoice, view
role:accountant, invoice, edit

# Avoid: broad allow + deny exceptions
role:accountant, invoice, *
role:accountant, invoice, delete, , deny
```

### 7. Document Complex Conditions

```
# Time-based access for support team
# Only during business hours (9 AM - 5 PM)
# Requires: ConditionResolver for time.between()
role:support, ticket, edit, time.between(09:00,17:00)
```

---

## Migration Guide

### From PHP Policies to DSL

#### Before (PHP)

```php
class InvoicePolicy implements PolicyInterface
{
    public function can($user, $action, $resource, $context): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }
        
        if ($action === 'view' && $user->hasRole('accountant')) {
            return true;
        }
        
        if ($action === 'edit' && $user->hasRole('accountant')) {
            return $resource->status !== 'paid';
        }
        
        return $resource->user_id === $user->id;
    }
}
```

#### After (DSL)

```json
[
  {"subject": "role:admin", "resource": "invoice", "action": "*", "effect": "allow"},
  {"subject": "role:accountant", "resource": "invoice", "action": "view", "effect": "allow"},
  {"subject": "role:accountant", "resource": "invoice", "action": "edit", "condition": "status!=paid", "effect": "allow"},
  {"subject": "user:*", "resource": "invoice", "action": "*", "condition": "owner", "effect": "allow"}
]
```

### Migration Steps

1. **Audit** - Document all existing permissions
2. **Convert** - Transform PHP logic to DSL rules
3. **Validate** - Use `DslValidator` to check syntax
4. **Test** - Verify behavior matches original
5. **Deploy** - Gradual rollout, one resource at a time
6. **Monitor** - Check logs for authorization issues
7. **Cleanup** - Remove PHP policy classes

---

## Examples

### Complete RBAC System

```json
[
  {"subject": "role:admin", "resource": "invoice", "action": "create", "effect": "allow"},
  {"subject": "role:admin", "resource": "invoice", "action": "edit", "effect": "allow"},
  {"subject": "role:admin", "resource": "invoice", "action": "delete", "effect": "allow"},
  {"subject": "role:admin", "resource": "invoice", "action": "view", "effect": "allow"},
  
  {"subject": "role:accountant", "resource": "invoice", "action": "view", "effect": "allow"},
  {"subject": "role:accountant", "resource": "invoice", "action": "edit", "effect": "allow"},
  {"subject": "role:accountant", "resource": "invoice", "action": "create", "effect": "allow"},
  
  {"subject": "role:sales", "resource": "client", "action": "create", "effect": "allow"},
  {"subject": "role:sales", "resource": "client", "action": "edit", "effect": "allow"},
  {"subject": "role:sales", "resource": "client", "action": "view", "effect": "allow"},
  {"subject": "role:sales", "resource": "invoice", "action": "view", "effect": "allow"},
  
  {"subject": "role:manager", "resource": "invoice", "action": "approve", "effect": "allow"},
  {"subject": "role:manager", "resource": "invoice", "action": "view", "effect": "allow"}
]
```

### Ownership Rules

```
# Users can manage their own resources
user:*, invoice, view, owner, allow
user:*, invoice, edit, owner, allow
user:*, invoice, delete, owner, allow

user:*, profile, view, owner, allow
user:*, profile, edit, owner, allow

user:*, document, view, owner, allow
user:*, document, edit, owner, allow
user:*, document, delete, owner, allow
```

### Department-Based Access

```json
[
  {"subject": "role:manager", "resource": "invoice", "action": "approve", "condition": "department==finance", "effect": "allow"},
  {"subject": "role:manager", "resource": "invoice", "action": "approve", "condition": "department==sales", "effect": "allow"},
  {"subject": "role:manager", "resource": "report", "action": "view", "condition": "department==finance", "effect": "allow"},
  {"subject": "role:manager", "resource": "budget", "action": "edit", "condition": "department==finance", "effect": "allow"}
]
```

### Status-Based Restrictions

```
# Accountants can only edit unpaid invoices
role:accountant, invoice, edit, status!=paid, allow
role:accountant, invoice, edit, status!=approved, allow

# Editors cannot modify published articles
role:editor, article, edit, status!=published, allow
role:editor, article, delete, status!=published, allow
```

### Deny Rules for Restrictions

```json
[
  {"subject": "role:admin", "resource": "invoice", "action": "*", "effect": "allow"},
  {"subject": "role:admin", "resource": "invoice:999", "action": "delete", "effect": "deny"},
  
  {"subject": "role:intern", "resource": "invoice", "action": "view", "effect": "allow"},
  {"subject": "role:intern", "resource": "invoice", "action": "delete", "effect": "deny"},
  {"subject": "role:intern", "resource": "client", "action": "delete", "effect": "deny"},
  
  {"subject": "user:*", "resource": "system", "action": "*", "effect": "deny"}
]
```

---

## API Reference

### DslParserInterface

```php
interface DslParserInterface
{
    /**
     * @return array<array{subject: string, resource: string, action: string, condition: ?string, effect: string}>
     */
    public function parse(string $content): array;
}
```

### PolicySourceInterface

```php
interface PolicySourceInterface
{
    /**
     * @return array<PolicyDefinition>
     */
    public function load(): array;
}
```

### PolicyDefinition

```php
final class PolicyDefinition
{
    public string $subject;
    public string $resource;
    public string $action;
    public ?string $condition;
    public string $effect;

    public function __construct(
        string $subject,
        string $resource,
        string $action,
        ?string $condition = null,
        string $effect = 'allow'
    );

    public static function fromArray(array $data): self;
    public function toArray(): array;
}
```

### DslPolicySource

```php
class DslPolicySource implements PolicySourceInterface
{
    public function __construct(
        DslParserInterface $parser,
        ?DslValidator $validator = null,
        ?string $content = null,
        ?string $filePath = null
    );

    public function load(): array;
    public function loadFromString(string $content): array;
    public function loadFromFile(string $filePath): array;
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
    public function __construct(array $policies);
    public function export(string $format = 'json'): string;
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
    public function hasWarnings(): bool;
    public function getErrors(): array;
    public function getWarnings(): array;
    public function getErrorCount(): int;
    public function getWarningCount(): int;
}
```

---

## Support

- GitHub Issues: https://github.com/authza/authza/issues
- Documentation: https://docs.authza.dev
- See also: [CLI Documentation](CLI.md)
