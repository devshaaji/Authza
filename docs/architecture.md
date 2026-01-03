# Authza

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Core Concepts](#core-concepts)
- [DSL (Domain-Specific Language)](#dsl-domain-specific-language)
- [CLI Commands](#cli-commands)
- [Architecture](#architecture)
- [Advanced Usage](#advanced-usage)
- [API Reference](#api-reference)
- [Testing](#testing)
- [Contributing](#contributing)
- [License](#license)

---

## Features

### Core Authorization
- ✅ `authorize()` and `can()` methods for policy evaluation
- ✅ Class-based policies per resource (e.g., `InvoicePolicy`)
- ✅ Hybrid ABAC + RBAC + relationship-based checks
- ✅ Precomputed **PermissionGraph** for fast decision lookups (graph caching only)
- ✅ Allow/Deny rules with conflict resolution

### DSL Support
- ✅ Human-readable DSL for non-developers
- ✅ JSON and line-based formats
- ✅ Import/Export capabilities
- ✅ Validation with detailed error reporting
- ✅ `PolicyDefinition` DTO as canonical format

### Standards Compliance
- ✅ PSR-16 cache integration (Redis, APCu, File, Array) — optional
- ✅ PSR-3 compliant logging for audit and debugging

### Developer Experience
- ✅ CLI tools for policy management
- ✅ Framework-agnostic, simple API
- ✅ Comprehensive test suite

---

## Requirements

- PHP 8.1 or higher
- Composer

---

## Installation

```bash
composer require authza/authza
```

---

## Quick Start

### 1. Basic Usage (PHP Policies)

```php
<?php
use Authza\Core\Authorization;
use Authza\Core\PolicyRegistry;
use Authza\Policies\InvoicePolicy;

// Create registry and register policies explicitly
$registry = new PolicyRegistry();
$registry->register('invoice', new InvoicePolicy());

// Create authorization instance (cache optional)
$authz = new Authorization($registry);

// Check permissions
if ($authz->can($user, 'edit', $invoice)) {
    echo "User can edit invoice.";
} else {
    echo "Access denied.";
}

// Or throw exception if denied
$authz->authorize($user, 'edit', $invoice); // Throws AuthorizationException if denied
```

### 2. Using DSL (No PHP Required)

Create `policies.json`:
```json
[
  {"subject": "role:admin", "resource": "invoice", "action": "create", "effect": "allow"},
  {"subject": "role:admin", "resource": "invoice", "action": "edit", "effect": "allow"},
  {"subject": "role:accountant", "resource": "invoice", "action": "view", "effect": "allow"},
  {"subject": "user:*", "resource": "invoice", "action": "view", "condition": "owner", "effect": "allow"}
]
```

Import via CLI:
```bash
./vendor/bin/authz import --file=policies.json
./vendor/bin/authz graph:build
```

Or programmatically:
```php
<?php
use Authza\Core\Graph\PermissionGraph;
use Authza\DSL\DslImporter;
use Authza\DSL\JsonDslParser;

$graph = new PermissionGraph($cache);
$importer = new DslImporter($graph);
$importer->importFromFile('policies.json');
```

### 3. Quick Start (explicit config)

```php
<?php
use Authza\Core\Authorization;
use Authza\Core\PolicyRegistry;

$registry = new PolicyRegistry();
// Register your policies explicitly
$registry->register('invoice', new \App\Policies\InvoicePolicy());
$registry->register('user', new \App\Policies\UserPolicy());

// Optional: provide PSR-16 cache and PSR-3 logger
$cache = null; // or new \Authza\Adapters\Cache\FileCache(__DIR__ . '/var/cache/authza');
$logger = null; // or a PSR-3 logger instance

$graph = $cache ? new \Authza\Core\Graph\PermissionGraph($cache) : null; // graph caching only

$authz = new Authorization($registry, $cache, $logger, $graph);

if ($authz->can($user, 'edit', $invoice)) {
    // Allowed
}
```

---

## Core Concepts

### Subjects are Application-defined

Authza does not impose a role model. Subjects (users, roles, service principals, etc.) are opaque, application-defined identifiers. The engine only evaluates:

```
subject → action → resource → decision
```

Examples like `role:admin` or `tenant:42:role:billing_admin` are illustrative, not prescriptive. Use any naming scheme that fits your domain.

> The examples in this documentation demonstrate capabilities, not recommended role structures.

### PermissionGraph is a Decision Cache

The PermissionGraph caches compiled rules for fast lookups. It does not define roles; it stores decisions like:

```
"role:admin:create:invoice:*" => true
"role:intern:delete:invoice:*" => false
```

Only the graph is cached. Individual `can()` results are evaluated fresh each call to avoid staleness.

### Resources

Resources represent **what** is being accessed. Implement `ResourceInterface`:

```php
<?php
use Authza\Interfaces\ResourceInterface;

class Invoice implements ResourceInterface
{
    public function getResourceType(): string
    {
        return 'invoice';
    }

    public function getResourceId(): string|int
    {
        return $this->id;
    }

    public function getOwnerId(): string|int
    {
        return $this->user_id;
    }
}
```

**DSL Resource Formats:**
- `invoice` - All invoices
- `invoice:123` - Specific invoice
- `invoice:*` - Any invoice (explicit wildcard)

### Actions

Actions define **what operation** is being performed:
- `create`, `read`, `view`, `edit`, `update`, `delete`, `approve`, `reject`
- Custom actions: `audit`, `export`, `finalize`

### Effects (Allow/Deny)

Each rule has an **effect** that determines the outcome:
- `allow` - Grants permission (default)
- `deny` - Explicitly denies permission (takes precedence)

```json
[
  {"subject": "role:admin", "resource": "invoice", "action": "*", "effect": "allow"},
  {"subject": "role:admin", "resource": "invoice:999", "action": "delete", "effect": "deny"}
]
```

### Conditions

Conditions add **context-aware** rules:

| Condition | Description | Example |
|-----------|-------------|---------|
| `owner` | User owns the resource | `user:*, invoice, edit, owner` |
| `key==value` | Context key equals value | `department==finance` |
| `key!=value` | Context key not equals value | `status!=paid` |

---

## DSL (Domain-Specific Language)

### JSON Format

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
  }
]
```

### Line-Based Format

```
# Format: subject, resource, action[, condition[, effect]]

# Admin permissions
role:admin, invoice, create
role:admin, invoice, edit
role:admin, invoice, delete, , allow

# Accountant with condition
role:accountant, invoice, edit, status!=paid, allow

# Ownership-based
user:*, invoice, delete, owner, allow

# Deny rule
role:intern, invoice, delete, , deny
```

### PolicyDefinition (Canonical Format)

All DSL rules are converted to `PolicyDefinition` objects:

```php
<?php
use Authza\DSL\PolicyDefinition;

$policy = new PolicyDefinition(
    subject: 'role:admin',
    resource: 'invoice',
    action: 'create',
    condition: null,
    effect: 'allow'
);

// Or from array
$policy = PolicyDefinition::fromArray([
    'subject' => 'role:admin',
    'resource' => 'invoice',
    'action' => 'create',
    'effect' => 'allow'
]);
```

### Policy Sources

Load policies from various sources using `PolicySourceInterface`:

```php
<?php
use Authza\DSL\DslPolicySource;
use Authza\DSL\JsonDslParser;
use Authza\Core\PolicyRegistry;

// Create policy source
$source = new DslPolicySource(
    parser: new JsonDslParser(),
    validator: new DslValidator(),
    filePath: 'policies.json'
);

// Register with PolicyRegistry - policies are loaded immediately
$registry = new PolicyRegistry();
$policies = $registry->registerSource($source);  // Returns loaded PolicyDefinition[]

// Policies are now available
$dslPolicies = $registry->getDslPolicies();
```

For complete DSL documentation, see [docs/DSL.md](docs/DSL.md).

---

## CLI Commands

The Authza CLI provides tools for managing policies:

```bash
# Import policies
./vendor/bin/authz import --file=policies.json

# Export policies
./vendor/bin/authz export --output=backup.json

# Validate policies
./vendor/bin/authz validate --file=policies.json --strict

# Build permission graph
./vendor/bin/authz graph:build --source=policies.json

# Check permissions
./vendor/bin/authz check --user=42 --resource=invoice:123 --action=edit --roles=admin

# List all policies
./vendor/bin/authz list --format=table

# Show statistics
./vendor/bin/authz stats

# Clear caches
./vendor/bin/authz cache:clear --confirm

# Invalidate graph
./vendor/bin/authz graph:invalidate
```

For complete CLI documentation, see [docs/CLI.md](docs/CLI.md).

---

## Architecture

```
Authza
├── Core/                          # Framework-agnostic core
│   ├── Authorization.php          # Main entry point (can, authorize)
│   ├── PolicyRegistry.php         # Maps resources → policies + DSL sources
│   └── Graph/
│       └── PermissionGraph.php    # Precomputed permission lookups (cached)
│
├── DSL/                           # Domain-Specific Language
│   ├── PolicyDefinition.php       # Canonical DTO format
│   ├── PolicySourceInterface.php  # Source abstraction
│   ├── DslPolicySource.php        # DSL file source implementation
│   ├── DslParserInterface.php     # Parser contract
│   ├── JsonDslParser.php          # JSON format parser
│   ├── LineDslParser.php          # Line format parser
│   ├── DslValidator.php           # Validation logic
│   ├── DslImporter.php            # Import to graph
│   └── DslExporter.php            # Export from graph
│
├── Condition/                     # Runtime condition evaluation
│   ├── ConditionResolverInterface.php
│   ├── ConditionResolverChain.php
│   └── Resolvers/
│       ├── OwnerConditionResolver.php
│       └── ComparisonConditionResolver.php
│
├── Match/                         # Pattern matching
│   ├── SubjectMatcher.php         # Match DSL subjects to users
│   └── ResourceMatcher.php        # Match DSL resources to objects
│
├── Interfaces/                    # Contracts
│   ├── SubjectInterface.php
│   ├── ResourceInterface.php
│   └── PolicyInterface.php
│
├── Adapters/                      # Optional integrations
│   ├── Cache/                     # PSR-16 cache adapters
│   └── Framework/                 # Framework integrations
│
├── Console/                       # CLI commands
│   ├── ImportCommand.php
│   ├── ExportCommand.php
│   ├── ValidateCommand.php
│   └── ...
│
└── Policies/                      # Example policies
    ├── InvoicePolicy.php
    ├── UserPolicy.php
    └── ClientPolicy.php
```

### Authorization Flow

```
Request
   ↓
authorize($user, $action, $resource, $context)
   ↓
┌─────────────────────────────────────┐
│         PermissionGraph             │
│  (Precomputed fast lookups; cached) │
│                                     │
│   ┌─────────────┐ ┌─────────────┐   │
│   │ PHP Policies│ │ DSL Policies│   │
│   └─────────────┘ └─────────────┘   │
│          ↑              ↑           │
│    PolicyRegistry  PolicySources    │
│                         ↑           │
│                    DSL Parser       │
└─────────────────────────────────────┘
   ↓
Result (allow/deny) + Logging (no result caching)
```

---

## Advanced Usage

### Custom Condition Resolvers

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

// Register resolver
$chain = new ConditionResolverChain([
    new OwnerConditionResolver(),
    new ComparisonConditionResolver(),
    new TimeConditionResolver(),
]);
```

### Multiple Policy Sources

```php
<?php
use Authza\Core\PolicyRegistry;
use Authza\DSL\DslPolicySource;
use Authza\DSL\JsonDslParser;
use Authza\DSL\LineDslParser;

$registry = new PolicyRegistry();

// PHP-based policies
$registry->register('invoice', new InvoicePolicy());
$registry->register('user', new UserPolicy());

// DSL sources
$registry->registerSource(new DslPolicySource(
    new JsonDslParser(),
    filePath: 'rbac_rules.json'
));

$registry->registerSource(new DslPolicySource(
    new LineDslParser(),
    filePath: 'ownership_rules.dsl'
));

// Load all DSL policies
$dslPolicies = $registry->getDslPolicies();
```

### Policy Versioning

```json
{
  "version": "1.2.0",
  "policies": [
    {"subject": "role:admin", "resource": "invoice", "action": "create", "effect": "allow"}
  ]
}
```

---

## API Reference

### Authorization

```php
class Authorization
{
    public function can(SubjectInterface $subject, string $action, ResourceInterface $resource, array $context = []): bool;
    public function authorize(SubjectInterface $subject, string $action, ResourceInterface $resource, array $context = []): void;
    public static function quickStart(array $config = []): self;
}
```

### PolicyRegistry

```php
class PolicyRegistry
{
    public function register(string $resourceType, PolicyInterface $policy): void;
    public function get(string $resourceType): ?PolicyInterface;
    public function registerSource(PolicySourceInterface $source): void;
    public function loadAll(): array; // Returns PolicyDefinition[]
    public function getDslPolicies(): array;
}
```

### PolicyDefinition

```php
final class PolicyDefinition
{
    public string $subject;    // role:admin | user:42 | user:*
    public string $resource;   // invoice | invoice:123
    public string $action;     // create | edit | delete
    public ?string $condition; // owner | department==finance
    public string $effect;     // allow | deny

    public static function fromArray(array $data): self;
    public function toArray(): array;
}
```

### PermissionGraph

```php
class PermissionGraph
{
    public function check(string $subjectId, string $action, string $resourceType, string $resourceId): ?bool;
    public function addRule(array $rule): void;
    public function getRules(): array;
    public function precomputeFromDsl(array $dslRules): void;
    public function invalidate(?string $subjectId = null): void;
    public function getStats(): array;
}
```

---

## Testing

```bash
# Run all tests
./vendor/bin/phpunit

# Run specific test suite
./vendor/bin/phpunit --filter=DSL

# Run with coverage
./vendor/bin/phpunit --coverage-html=coverage
```

---

## Contributing

1. Fork the repository
2. Create a feature branch
3. Write tests for your changes
4. Ensure all tests pass
5. Submit a pull request

---

## License

MIT License - see [LICENSE](LICENSE) file for details.

---

## Support

- GitHub Issues: [https://github.com/authza/authza/issues](https://github.com/authza/authza/issues)
- Documentation: [https://docs.authza.dev](https://docs.authza.dev)
