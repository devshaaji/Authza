# Authza CLI Documentation

Complete guide to using the Authza Command-Line Interface for managing authorization policies, permissions, and testing.

---

## Table of Contents

- [Installation](#installation)
- [Configuration](#configuration)
- [Commands Reference](#commands-reference)
  - [init](#init)
  - [import](#import)
  - [export](#export)
  - [validate](#validate)
  - [graph:build](#graphbuild)
  - [graph:invalidate](#graphinvalidate)
  - [cache:clear](#cacheclear)
  - [check](#check)
  - [list](#list)
  - [stats](#stats)
- [DSL Format Reference](#dsl-format-reference)
- [Common Workflows](#common-workflows)
- [CI/CD Integration](#cicd-integration)
- [Troubleshooting](#troubleshooting)

---

## Installation

### Requirements

- PHP 8.1 or higher
- Composer

### Install via Composer

```bash
composer require authza/authza
```

The `authz` CLI tool will be available in `vendor/bin/authz`.

### Global Installation

```bash
composer global require authza/authza
```

### Verify Installation

```bash
./vendor/bin/authz --version
./vendor/bin/authz list
```

---

## Configuration

The CLI requires a configuration file to operate. Both the CLI and your application code use the **same configuration file**, ensuring consistent behavior.

### Creating Configuration

Use the `init` command to create a configuration file:

```bash
./vendor/bin/authza init
```

This creates `authza.config.php` in your current directory and **remembers the config path** for future commands.

### Config Path Resolution

After running `init`, the CLI remembers your config path in a `.authza` file. You can then run commands without specifying `--config`:

```bash
# First time: initialize config
./vendor/bin/authza init

# Subsequent commands: no --config needed!
./vendor/bin/authza list
./vendor/bin/authza stats
./vendor/bin/authza check --user=1 --resource=invoice:1 --action=view
```

### Configuration Resolution Order

| Priority | Method | Description |
|----------|--------|-------------|
| 1 | `--config` option | Explicit path via CLI argument |
| 2 | `AUTHZA_CONFIG` env | Environment variable |
| 3 | `.authza` file | Remembered path from `init` command |

### Re-initializing

If you run `init` again when a config is already set up, you'll be prompted to confirm:

```bash
$ ./vendor/bin/authza init
⚠ A configuration is already set up: /path/to/authza.config.php

Do you want to replace it with a new configuration? [y/N]
```

Use `--force` to skip the confirmation:

```bash
./vendor/bin/authza init --force
```

### Explicit Config (Production)

For production environments, you may prefer explicit config specification:

```bash
# Using --config option
./vendor/bin/authz --config=/path/to/authza.config.php list

# Using environment variable
export AUTHZA_CONFIG=/path/to/authza.config.php
./vendor/bin/authz list
```

### Configuration File Structure

```php
<?php
// authza.config.php

use Authza\Adapters\Cache\ArrayCache;
use Authza\Adapters\Cache\FileCache;
use App\Policies\UserPolicy;
use App\Policies\InvoicePolicy;

return [
    /**
     * Cache Configuration (PSR-16 SimpleCache) - Optional
     * 
     * If not provided, policies are evaluated directly without caching.
     * For production, providing a cache improves performance.
     */
    'cache' => [
        // 'instance' => new ArrayCache(),
        // 'instance' => new FileCache(__DIR__ . '/var/cache/authza'),
        // 'instance' => new \App\Cache\RedisCache($redis),
    ],

    /**
     * Logger Configuration (PSR-3 Logger) - Optional
     * 
     * If not provided, a NullLogger is used.
     */
    'logger' => [
        // 'instance' => $monologLogger,
    ],

    /**
     * Permission Graph Storage
     */
    'graph' => [
        'storage' => __DIR__ . '/var/storage/authza_graph.json',
    ],

    /**
     * PHP Policy Classes (Explicit Registration)
     * 
     * SECURITY: All policies must be explicitly registered.
     * No auto-discovery to prevent loading malicious code.
     */
    'policies' => [
        'register' => [
            'user' => UserPolicy::class,
            'invoice' => InvoicePolicy::class,
            // Or with dependencies:
            'document' => new \App\Policies\DocumentPolicy($dependency),
        ],
    ],

    /**
     * DSL Policy Files (Explicit List)
     * 
     * SECURITY: All DSL files must be explicitly listed.
     */
    'dsl' => [
        'files' => [
            __DIR__ . '/rules/rbac.json',
            __DIR__ . '/rules/permissions.dsl',
        ],
    ],

    /**
     * Bootstrap Callback (Optional)
     */
    'bootstrap' => function(\Authza\Console\ServiceContainer $container) {
        // Custom initialization
    },
];
```

### Security Best Practices

> **⚠️ Important Security Notes:**
> 
> 1. **No Policy Auto-Discovery**: Policy classes must be explicitly registered in `policies.register` to prevent loading malicious code.
> 
> 2. **Explicit DSL Files**: All DSL rule files must be explicitly listed in `dsl.files`.
> 
> 3. **Explicit Config Path**: Always use `--config` option or `AUTHZA_CONFIG` environment variable.

### Environment Variables

Override configuration with environment variables:

| Variable | Config Key | Description |
|----------|------------|-------------|
| `AUTHZA_CONFIG` | - | Path to config file (required) |
| `AUTHZA_GRAPH_STORAGE` | `graph.storage` | Graph storage file path |
| `AUTHZA_DSL_FILES` | `dsl.files` | Comma-separated DSL file paths |

> **Note:** Cache and logger must be provided via config file - they cannot be set via environment variables.

### Using the Same Config in Application Code

```php
<?php
use Authza\Core\AuthzaFactory;
use Authza\Adapters\Cache\ArrayCache;
use Authza\Adapters\Cache\FileCache;

// Option 1: Explicit config file path (recommended)
$authz = AuthzaFactory::createFromFile(__DIR__ . '/authza.config.php');

// Option 2: From array (cache instance is OPTIONAL)
$authz = AuthzaFactory::create([
    'cache' => [
        'instance' => new FileCache('/tmp/authza_cache'),
    ],
    'policies' => [
        'register' => [
            'invoice' => \App\Policies\InvoicePolicy::class,
        ],
    ],
    'dsl' => ['files' => [__DIR__ . '/rules.json']],
]);

// Now use the same Authorization instance as CLI
if ($authz->can($user, 'edit', $invoice)) {
    // Allowed
}
```

---

## Commands Reference

### init

Initialize a new Authza configuration file.

**Usage:**
```bash
authz init [--output=<path>] [--force]
```

**Options:**

| Option | Short | Description |
|--------|-------|-------------|
| `--output` | `-o` | Output file path (default: `authza.config.php`) |
| `--force` | `-f` | Overwrite existing configuration file |

**Examples:**

```bash
# Create default config file
authz init

# Create config in specific location
authz init --output=config/authza.php

# Overwrite existing config
authz init --force
```

---

### import

Import DSL rules into the permission graph.

**Usage:**
```bash
authz import --file=<path> [--format=<json|line>] [--dry-run] [-v]
```

**Options:**

| Option | Short | Description |
|--------|-------|-------------|
| `--file` | `-f` | Path to DSL file (required) |
| `--format` | | Force format (json\|line), auto-detects by extension |
| `--dry-run` | | Validate without importing |
| `-v` | `--verbose` | Show detailed output |

**Examples:**

```bash
# Import JSON DSL file
authz import --file=policies.json

# Import line-based DSL
authz import --file=policies.dsl

# Dry-run to validate before importing
authz import --file=policies.json --dry-run

# Import with verbose output
authz import --file=policies.json -v
```

**Exit Codes:**
- `0`: Success
- `1`: Validation error
- `2`: Import error

---

### export

Export policies from the permission graph to DSL format.

**Usage:**
```bash
authz export --output=<path> [--format=<json|line>] [--filter=<pattern>] [-v]
```

**Options:**

| Option | Short | Description |
|--------|-------|-------------|
| `--output` | `-o` | Output file path (required) |
| `--format` | | Export format (json\|line), default: json |
| `--filter` | | Filter rules by pattern |
| `-v` | `--verbose` | Show detailed output |

**Examples:**

```bash
# Export to JSON
authz export --output=backup.json

# Export to line format
authz export --output=backup.dsl --format=line

# Export only admin rules
authz export --output=admin_rules.json --filter=role:admin

# Export invoice-related rules
authz export --output=invoice_policies.json --filter=invoice

# Export deny rules only
authz export --output=deny_rules.json --filter=deny
```

**Output Formats:**

JSON format includes the `effect` field:
```json
[
  {
    "subject": "role:admin",
    "resource": "invoice",
    "action": "create",
    "effect": "allow"
  }
]
```

Line format:
```
role:admin, invoice, create, , allow
role:admin, invoice, delete, , deny
```

---

### validate

Validate DSL rules without importing.

**Usage:**
```bash
authz validate --file=<path> [--format=<json|line>] [--strict]
```

**Options:**

| Option | Short | Description |
|--------|-------|-------------|
| `--file` | `-f` | Path to DSL file (required) |
| `--format` | | Force format (json\|line) |
| `--strict` | | Treat warnings as errors |

**Validation Checks:**
- ✅ Syntax errors
- ✅ Invalid subject format (must be `role:*` or `user:*`)
- ✅ Missing required fields (subject, resource, action)
- ✅ Invalid effect (must be `allow` or `deny`)
- ✅ Duplicate rules
- ✅ Conflicting rules (wildcard vs specific)
- ⚠️ Non-standard resource types (warning)
- ⚠️ Non-standard actions (warning)

**Examples:**

```bash
# Validate DSL file
authz validate --file=policies.json

# Validate with strict mode (warnings become errors)
authz validate --file=policies.json --strict

# Validate line-based DSL
authz validate --file=policies.dsl --format=line
```

**Exit Codes:**
- `0`: Valid (no errors)
- `1`: Invalid (has errors)

---

### graph:build

Build or rebuild the permission graph from DSL policies.

**Usage:**
```bash
authz graph:build [--source=<path>] [--clear] [-v]
```

**Options:**

| Option | Short | Description |
|--------|-------|-------------|
| `--source` | `-s` | DSL file to build from |
| `--clear` | `-c` | Clear existing graph before building |
| `-v` | `--verbose` | Show detailed output |

**Examples:**

```bash
# Build graph from current policies
authz graph:build

# Build from specific DSL file
authz graph:build --source=policies.json

# Clear and rebuild
authz graph:build --source=policies.json --clear

# Build with verbose output
authz graph:build --source=policies.json -v
```

---

### graph:invalidate

Invalidate permission graph cache.

**Usage:**
```bash
authz graph:invalidate [--subject=<id>]
```

**Options:**

| Option | Short | Description |
|--------|-------|-------------|
| `--subject` | `-s` | Invalidate specific subject only |

**Examples:**

```bash
# Invalidate entire graph cache
authz graph:invalidate

# Invalidate specific user
authz graph:invalidate --subject=user:42

# Invalidate specific role
authz graph:invalidate --subject=role:admin
```

---

### cache:clear

Clear authorization caches.

**Usage:**
```bash
authz cache:clear [--type=<all|decisions|graph>] [--confirm]
```

**Options:**

| Option | Short | Description |
|--------|-------|-------------|
| `--type` | `-t` | Cache type (all\|decisions\|graph), default: all |
| `--confirm` | `-c` | Skip confirmation prompt |

**Examples:**

```bash
# Clear all caches (with confirmation prompt)
authz cache:clear

# Clear specific cache type
authz cache:clear --type=graph

# Clear without confirmation
authz cache:clear --confirm

# Clear decision cache only
authz cache:clear --type=decisions --confirm
```

---

### check

Test permission checks from the command line.

**Usage:**
```bash
authz check --user=<id> --resource=<type:id> --action=<action> [--roles=<roles>] [--context=<json>] [-v]
```

**Options:**

| Option | Short | Description |
|--------|-------|-------------|
| `--user` | `-u` | User/subject ID (required) |
| `--resource` | `-r` | Resource as "type:id" (required) |
| `--action` | `-a` | Action to check (required) |
| `--roles` | | Comma-separated roles |
| `--context` | | JSON context data |
| `-v` | `--verbose` | Show evaluation trace |

**Examples:**

```bash
# Simple permission check
authz check --user=42 --resource=invoice:123 --action=edit

# Check with roles
authz check --user=42 --resource=invoice:123 --action=edit --roles=admin,accountant

# Check with context (for conditions like department==finance)
authz check --user=42 --resource=invoice:123 --action=approve \
    --context='{"department":"finance"}'

# Check ownership condition
authz check --user=42 --resource=invoice:123 --action=edit \
    --context='{"is_owner":true}'

# Verbose output shows evaluation details
authz check --user=42 --resource=invoice:123 --action=edit --roles=admin -v
```

**Exit Codes:**
- `0`: Allowed ✓
- `1`: Denied ✗

---

### list

List all policies in the permission graph.

**Usage:**
```bash
authz list [--filter=<pattern>] [--format=<table|json|yaml>] [--resource=<type>] [--subject=<id>]
```

**Aliases:** `policy:list`

**Options:**

| Option | Short | Description |
|--------|-------|-------------|
| `--filter` | `-f` | Filter by subject, resource, action, or effect |
| `--format` | | Output format (table\|json), default: table |
| `--resource` | `-r` | Filter by resource type |
| `--subject` | `-s` | Filter by subject |

**Examples:**

```bash
# List all policies in table format
authz list

# List in JSON format
authz list --format=json

# Filter by admin role
authz list --filter=role:admin

# Filter by resource type
authz list --resource=invoice

# Filter by subject
authz list --subject=role:admin

# Filter deny rules
authz list --filter=deny

# Combined filters
authz list --resource=invoice --filter=edit
```

**Table Output:**
```
+-------------+----------+--------+-----------+--------+
| Subject     | Resource | Action | Condition | Effect |
+-------------+----------+--------+-----------+--------+
| role:admin  | invoice  | create |           | allow  |
| role:admin  | invoice  | edit   |           | allow  |
| role:admin  | invoice  | delete |           | deny   |
| user:*      | invoice  | view   | owner     | allow  |
+-------------+----------+--------+-----------+--------+
```

---

### stats

Show permission graph statistics.

**Usage:**
```bash
authz stats [--format=<table|json>]
```

**Options:**

| Option | Short | Description |
|--------|-------|-------------|
| `--format` | `-f` | Output format (table\|json), default: table |

**Examples:**

```bash
# Show statistics in table format
authz stats

# Show statistics in JSON format
authz stats --format=json
```

**Output includes:**
- Total rules count
- Rules by resource type
- Rules by subject type (role/user)
- Allow vs deny breakdown
- Cache information

---

## DSL Format Reference

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

```
# Syntax: subject, resource, action[, condition[, effect]]
# Comments start with #
# Empty lines are ignored

# === Admin Permissions ===
role:admin, invoice, create
role:admin, invoice, edit
role:admin, invoice, view

# === Deny Rules ===
role:intern, invoice, delete, , deny

# === Conditional Rules ===
role:accountant, invoice, edit, status!=paid, allow

# === Ownership Rules ===
user:*, invoice, view, owner
user:*, invoice, edit, owner
user:*, invoice, delete, owner, allow
```

### Field Reference

| Field | Required | Description | Examples |
|-------|----------|-------------|----------|
| `subject` | Yes | Who is requesting access | `role:admin`, `user:42`, `user:*` |
| `resource` | Yes | What is being accessed | `invoice`, `invoice:123`, `invoice:*` |
| `action` | Yes | Operation being performed | `create`, `edit`, `delete`, `view` |
| `condition` | No | Context-aware condition | `owner`, `department==finance`, `status!=paid` |
| `effect` | No | Allow or deny (default: allow) | `allow`, `deny` |

### Subject Patterns

| Pattern | Description |
|---------|-------------|
| `role:admin` | Users with admin role |
| `role:*` | Any role |
| `user:42` | Specific user with ID 42 |
| `user:*` | Any authenticated user |

### Resource Patterns

| Pattern | Description |
|---------|-------------|
| `invoice` | All invoices (type-level) |
| `invoice:123` | Specific invoice with ID 123 |
| `invoice:*` | Any invoice (explicit wildcard) |

### Condition Patterns

| Pattern | Description | Context Required |
|---------|-------------|------------------|
| `owner` | User owns the resource | `is_owner: true` |
| `key==value` | Context key equals value | `key: value` |
| `key!=value` | Context key not equals | `key: other` |

---

## Common Workflows

### 1. Initial Setup

```bash
# Create policies file
cat > policies.json << 'EOF'
[
  {"subject": "role:admin", "resource": "invoice", "action": "create", "effect": "allow"},
  {"subject": "role:admin", "resource": "invoice", "action": "edit", "effect": "allow"},
  {"subject": "role:admin", "resource": "invoice", "action": "delete", "effect": "allow"},
  {"subject": "role:admin", "resource": "invoice", "action": "view", "effect": "allow"},
  {"subject": "role:accountant", "resource": "invoice", "action": "view", "effect": "allow"},
  {"subject": "user:*", "resource": "invoice", "action": "view", "condition": "owner", "effect": "allow"}
]
EOF

# Validate
authz validate --file=policies.json --strict

# Import
authz import --file=policies.json

# Build graph
authz graph:build

# Verify
authz list
authz stats
```

### 2. Policy Development Workflow

```bash
# Step 1: Edit policies
vim policies.json

# Step 2: Validate
authz validate --file=policies.json --strict

# Step 3: Dry-run import
authz import --file=policies.json --dry-run

# Step 4: Import
authz import --file=policies.json

# Step 5: Rebuild graph
authz graph:build --clear

# Step 6: Test
authz check --user=1 --resource=invoice:123 --action=edit --roles=admin

# Step 7: Verify
authz list --format=table
```

### 3. Production Deployment

```bash
#!/bin/bash
set -e

echo "=== Authza Policy Deployment ==="

# Backup current policies
BACKUP_FILE="backup_$(date +%Y%m%d_%H%M%S).json"
authz export --output="$BACKUP_FILE"
echo "✓ Backed up to $BACKUP_FILE"

# Validate new policies
authz validate --file=new_policies.json --strict
echo "✓ Validation passed"

# Import new policies
authz import --file=new_policies.json
echo "✓ Policies imported"

# Rebuild graph
authz graph:build --clear
echo "✓ Graph rebuilt"

# Clear decision caches
authz cache:clear --type=decisions --confirm
echo "✓ Caches cleared"

# Verify critical permissions
authz check --user=admin --resource=system:config --action=edit --roles=admin
echo "✓ Critical permissions verified"

echo "=== Deployment Complete ==="
```

### 4. Debugging Permissions

```bash
# Check current statistics
authz stats

# List all policies
authz list --format=json > debug_policies.json

# Test specific permission with verbose output
authz check --user=42 --resource=invoice:123 --action=edit --roles=accountant -v

# Check if deny rules exist
authz list --filter=deny

# Export for review
authz export --output=review.json
```

### 5. Rollback Procedure

```bash
#!/bin/bash
BACKUP_FILE=$1

if [ -z "$BACKUP_FILE" ]; then
    echo "Usage: rollback.sh <backup_file>"
    exit 1
fi

echo "Rolling back to $BACKUP_FILE..."

# Clear current graph
authz graph:invalidate

# Import backup
authz import --file="$BACKUP_FILE"

# Rebuild
authz graph:build --clear

# Clear caches
authz cache:clear --confirm

echo "Rollback complete"
```

---

## CI/CD Integration

### GitHub Actions

```yaml
name: Authza Policy Validation

on:
  pull_request:
    paths:
      - 'policies/**'
      - '*.json'
      - '*.dsl'

jobs:
  validate:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'
      
      - name: Install dependencies
        run: composer install --no-dev
      
      - name: Validate policies
        run: ./vendor/bin/authz validate --file=policies/production.json --strict
      
      - name: Dry-run import
        run: ./vendor/bin/authz import --file=policies/production.json --dry-run
      
      - name: Test critical permissions
        run: |
          ./vendor/bin/authz import --file=policies/production.json
          ./vendor/bin/authz graph:build
          ./vendor/bin/authz check --user=admin --resource=system:config --action=edit --roles=admin
```

### GitLab CI

```yaml
stages:
  - validate
  - deploy

validate-policies:
  stage: validate
  script:
    - composer install --no-dev
    - ./vendor/bin/authz validate --file=policies/production.json --strict
    - ./vendor/bin/authz import --file=policies/production.json --dry-run
  rules:
    - changes:
        - policies/**/*

deploy-policies:
  stage: deploy
  script:
    - composer install --no-dev
    - ./vendor/bin/authz import --file=policies/production.json
    - ./vendor/bin/authz graph:build --clear
    - ./vendor/bin/authz cache:clear --confirm
  rules:
    - if: $CI_COMMIT_BRANCH == "main"
      changes:
        - policies/**/*
```

### Jenkins Pipeline

```groovy
pipeline {
    agent any
    
    stages {
        stage('Validate') {
            steps {
                sh 'composer install --no-dev'
                sh './vendor/bin/authz validate --file=policies/production.json --strict'
            }
        }
        
        stage('Test') {
            steps {
                sh './vendor/bin/authz import --file=policies/production.json --dry-run'
            }
        }
        
        stage('Deploy') {
            when {
                branch 'main'
            }
            steps {
                sh './vendor/bin/authz export --output=backup_${BUILD_NUMBER}.json'
                sh './vendor/bin/authz import --file=policies/production.json'
                sh './vendor/bin/authz graph:build --clear'
                sh './vendor/bin/authz cache:clear --confirm'
            }
        }
    }
    
    post {
        failure {
            sh './vendor/bin/authz import --file=backup_${BUILD_NUMBER}.json || true'
        }
    }
}
```

---

## Troubleshooting

### Command Not Found

**Problem:** `authz: command not found`

**Solutions:**
```bash
# Use full path
./vendor/bin/authz list

# Add to PATH
export PATH="$PATH:./vendor/bin"

# Use PHP directly
php vendor/bin/authz list

# Use composer
composer exec authz list
```

### Permission Denied

**Problem:** `Permission denied` when running authz

**Solution:**
```bash
chmod +x vendor/bin/authz
```

### Invalid JSON Errors

**Problem:** `Invalid JSON: Syntax error`

**Solutions:**
```bash
# Validate JSON syntax
cat policies.json | php -r "json_decode(file_get_contents('php://stdin')); echo json_last_error_msg();"

# Use jq to format/validate
cat policies.json | jq .

# Common issues:
# - Trailing commas
# - Missing quotes
# - Unescaped characters
```

### Subject Format Errors

**Problem:** `Invalid subject format`

**Solution:**
```bash
# Wrong
admin, invoice, create

# Correct - must have role: or user: prefix
role:admin, invoice, create
user:42, invoice, create
```

### Effect Field Errors

**Problem:** `Invalid effect: must be 'allow' or 'deny'`

**Solution:**
```json
// Wrong
{"subject": "role:admin", "resource": "invoice", "action": "create", "effect": "granted"}

// Correct
{"subject": "role:admin", "resource": "invoice", "action": "create", "effect": "allow"}
```

### Cache Issues

**Problem:** Policies not updating after import

**Solution:**
```bash
# Clear all caches
authz cache:clear --confirm

# Rebuild graph
authz graph:build --clear

# Verify
authz list
```

### Graph Storage Errors

**Problem:** `Failed to write to graph storage`

**Solution:**
```bash
# Check directory exists
mkdir -p storage/

# Check permissions
chmod 755 storage/
chmod 644 storage/graph.json

# Check disk space
df -h
```

---

## Best Practices

1. **Always validate before importing** - Use `--dry-run` or `validate` command
2. **Backup before changes** - Export current policies before modifications
3. **Use version control** - Track DSL files in Git
4. **Test in staging first** - Verify policies before production
5. **Use strict mode in CI** - `--strict` catches potential issues
6. **Document complex rules** - Add comments to DSL files
7. **Regular audits** - Export and review policies periodically
8. **Monitor statistics** - Track policy growth with `stats` command
9. **Clear caches after changes** - Ensure new policies take effect
10. **Use deny rules sparingly** - Prefer explicit allow rules

---

## Support

- GitHub Issues: https://github.com/authza/authza/issues
- Documentation: https://docs.authza.dev
- CLI Help: `authz --help` or `authz <command> --help`
