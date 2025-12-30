# Authza CLI Documentation

Complete guide to using the Authza Command-Line Interface for managing authorization policies, permissions, and testing.

---

## Table of Contents

- [Installation](#installation)
- [Configuration](#configuration)
- [Commands Reference](#commands-reference)
  - [import](#import)
  - [export](#export)
  - [validate](#validate)
  - [graph:build](#graphbuild)
  - [graph:invalidate](#graphinvalidate)
  - [cache:clear](#cacheclear)
  - [check](#check)
  - [list](#list)
  - [stats](#stats)
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

---

## Configuration

### Configuration File

Create an `authza_config.php` file in your project root:

```php
<?php
return [
    'cache' => [
        'adapter' => 'file',
        'path' => __DIR__ . '/cache'
    ],
    'graph' => [
        'storage' => __DIR__ . '/storage/graph.json'
    ],
    'policies' => [
        'namespace' => 'App\\Policies',
        'path' => __DIR__ . '/src/Policies'
    ]
];
```

### Environment Variables

Alternatively, use environment variables:

```bash
export AUTHZA_CACHE_ADAPTER=file
export AUTHZA_CACHE_PATH=/var/cache/authza
export AUTHZA_GRAPH_STORAGE=/var/lib/authza/graph.json
export AUTHZA_POLICY_NAMESPACE=App\\Policies
```

---

## Commands Reference

### import

Import DSL rules into the permission graph.

**Usage:**
```bash
authz import --file=<path> [--format=<json|line>] [--dry-run] [-v]
```

**Options:**
- `--file`, `-f`: Path to DSL file (required)
- `--format`: Force specific format (json|line), auto-detect if not provided
- `--dry-run`: Validate without importing
- `-v`, `--verbose`: Show detailed output

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

Export policies to DSL format.

**Usage:**
```bash
authz export --output=<path> [--format=<json|line>] [--filter=<pattern>] [-v]
```

**Options:**
- `--output`, `-o`: Output file path (required)
- `--format`: Export format (json|line), default: json
- `--filter`: Filter rules by pattern (e.g., "role:admin", "invoice")
- `-v`, `--verbose`: Show detailed output

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
```

---

### validate

Validate DSL rules without importing.

**Usage:**
```bash
authz validate --file=<path> [--format=<json|line>] [--strict]
```

**Options:**
- `--file`, `-f`: Path to DSL file (required)
- `--format`: Force specific format (json|line)
- `--strict`: Treat warnings as errors

**Examples:**

```bash
# Validate DSL file
authz validate --file=policies.json

# Validate with strict mode
authz validate --file=policies.json --strict

# Validate line-based DSL
authz validate --file=policies.dsl --format=line
```

**Exit Codes:**
- `0`: Valid
- `1`: Invalid

---

### graph:build

Build or rebuild the permission graph.

**Usage:**
```bash
authz graph:build [--source=<path>] [--clear] [-v]
```

**Options:**
- `--source`, `-s`: DSL file to build from
- `--clear`, `-c`: Clear existing graph before building
- `-v`, `--verbose`: Show detailed output

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
- `--subject`, `-s`: Invalidate specific subject only

**Examples:**

```bash
# Invalidate entire graph cache
authz graph:invalidate

# Invalidate specific subject
authz graph:invalidate --subject=user:42
```

---

### cache:clear

Clear all caches.

**Usage:**
```bash
authz cache:clear [--type=<all|decisions|graph>] [--confirm]
```

**Options:**
- `--type`, `-t`: Type of cache to clear (all|decisions|graph), default: all
- `--confirm`, `-c`: Skip confirmation prompt

**Examples:**

```bash
# Clear all caches (with confirmation)
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

Test permission checks.

**Usage:**
```bash
authz check --user=<id> --resource=<type:id> --action=<action> [--roles=<roles>] [--context=<json>] [-v]
```

**Options:**
- `--user`, `-u`: User/subject ID (required)
- `--resource`, `-r`: Resource in format "type:id" (e.g., "invoice:123") (required)
- `--action`, `-a`: Action to check (e.g., "edit", "view") (required)
- `--roles`: Comma-separated roles for the user
- `--context`: JSON context data
- `-v`, `--verbose`: Show detailed evaluation trace

**Examples:**

```bash
# Simple permission check
authz check --user=42 --resource=invoice:123 --action=edit

# Check with roles
authz check --user=42 --resource=invoice:123 --action=edit --roles=admin,accountant

# Check with context
authz check --user=42 --resource=invoice:123 --action=approve --context='{"department":"finance"}'

# Check with verbose output
authz check --user=42 --resource=invoice:123 --action=edit --roles=admin -v
```

**Exit Codes:**
- `0`: Allowed
- `1`: Denied

---

### list

List all policies.

**Usage:**
```bash
authz list [--filter=<pattern>] [--format=<table|json|yaml>] [--resource=<type>] [--subject=<id>]
```

**Aliases:** `policy:list`

**Options:**
- `--filter`, `-f`: Filter by subject, resource, or action
- `--format`: Output format (table|json|yaml), default: table
- `--resource`, `-r`: Filter by resource type
- `--subject`, `-s`: Filter by subject

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

# Combined filters
authz list --resource=invoice --filter=edit
```

---

### stats

Show permission graph statistics.

**Usage:**
```bash
authz stats [--format=<table|json>]
```

**Options:**
- `--format`, `-f`: Output format (table|json), default: table

**Examples:**

```bash
# Show statistics in table format
authz stats

# Show statistics in JSON format
authz stats --format=json
```

**Statistics Include:**
- Total rules count
- Rules by resource type
- Rules by subject type (role/user)
- Cache information

---

## Common Workflows

### 1. Policy Development Workflow

```bash
# Step 1: Create/edit DSL file
# Edit your policies.json file

# Step 2: Validate policies
authz validate --file=policies.json --strict

# Step 3: Dry-run import
authz import --file=policies.json --dry-run

# Step 4: Import policies
authz import --file=policies.json

# Step 5: Build permission graph
authz graph:build

# Step 6: Test permissions
authz check --user=1 --resource=invoice:123 --action=edit --roles=admin

# Step 7: List policies to verify
authz list --format=table
```

### 2. Production Deployment Workflow

```bash
#!/bin/bash
set -e

# Backup current policies
authz export --output=backup_$(date +%Y%m%d_%H%M%S).json

# Validate new policies
authz validate --file=new_policies.json --strict

# Import new policies
authz import --file=new_policies.json

# Rebuild permission graph
authz graph:build --clear

# Clear caches
authz cache:clear --type=decisions --confirm

# Verify critical permissions
authz check --user=admin --resource=system:config --action=edit --roles=admin

echo "Deployment completed successfully"
```

### 3. Debugging Workflow

```bash
# Check current statistics
authz stats

# List all policies
authz list --format=json > current_policies.json

# Test specific permission with verbose output
authz check --user=42 --resource=invoice:123 --action=edit --roles=accountant -v

# Export policies for review
authz export --output=debug_export.json

# Validate policies
authz validate --file=debug_export.json
```

### 4. Audit and Compliance Workflow

```bash
# Export all policies for audit
authz export --output=audit_$(date +%Y%m%d).json --format=json

# Export specific role policies
authz export --output=admin_policies.json --filter=role:admin
authz export --output=user_policies.json --filter=role:user

# Generate statistics report
authz stats --format=json > stats_$(date +%Y%m%d).json

# List all policies in readable format
authz list --format=table > policies_report.txt
```

---

## CI/CD Integration

### GitHub Actions Example

```yaml
name: Authza Policy Validation

on:
  pull_request:
    paths:
      - 'policies/**'

jobs:
  validate:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'
      
      - name: Install dependencies
        run: composer install
      
      - name: Validate policies
        run: ./vendor/bin/authz validate --file=policies/production.json --strict
      
      - name: Test critical permissions
        run: |
          ./vendor/bin/authz check --user=admin --resource=system:config --action=edit --roles=admin
          ./vendor/bin/authz check --user=user --resource=invoice:1 --action=view --roles=user
```

### GitLab CI Example

```yaml
validate-policies:
  stage: test
  script:
    - composer install
    - ./vendor/bin/authz validate --file=policies/production.json --strict
    - ./vendor/bin/authz import --file=policies/production.json --dry-run
  only:
    - merge_requests
    - main
```

### Jenkins Pipeline Example

```groovy
pipeline {
    agent any
    
    stages {
        stage('Install') {
            steps {
                sh 'composer install'
            }
        }
        
        stage('Validate Policies') {
            steps {
                sh './vendor/bin/authz validate --file=policies/production.json --strict'
            }
        }
        
        stage('Import Policies') {
            when {
                branch 'main'
            }
            steps {
                sh './vendor/bin/authz import --file=policies/production.json'
                sh './vendor/bin/authz graph:build --clear'
            }
        }
        
        stage('Test Permissions') {
            steps {
                sh './vendor/bin/authz check --user=admin --resource=system:config --action=edit --roles=admin'
            }
        }
    }
}
```

---

## Troubleshooting

### Command Not Found

**Problem:** `authz: command not found`

**Solution:**
```bash
# Use full path
./vendor/bin/authz list

# Or add to PATH
export PATH="$PATH:./vendor/bin"
authz list

# Or use composer run
composer exec authz list
```

### File Permission Errors

**Problem:** `Failed to write to file`

**Solution:**
```bash
# Check directory permissions
ls -la storage/

# Create directory if needed
mkdir -p storage/
chmod 755 storage/

# Check file permissions
chmod 644 storage/graph.json
```

### Invalid JSON Errors

**Problem:** `Invalid JSON: Syntax error`

**Solution:**
```bash
# Validate JSON syntax
cat policies.json | jq .

# Use online JSON validator
# Fix JSON syntax errors
# Re-run validation
authz validate --file=policies.json
```

### Cache Issues

**Problem:** Policies not updating

**Solution:**
```bash
# Clear all caches
authz cache:clear --confirm

# Rebuild graph
authz graph:build --clear

# Verify changes
authz list --format=table
```

### Import Validation Failures

**Problem:** Rules fail validation during import

**Solution:**
```bash
# Run validation with verbose output
authz validate --file=policies.json

# Check for missing fields
# Ensure all rules have: subject, resource, action

# Example valid rule:
# {"subject": "role:admin", "resource": "invoice", "action": "create"}
```

---

## DSL Format Examples

### JSON DSL Format

```json
[
  {
    "subject": "role:admin",
    "resource": "invoice",
    "action": "create"
  },
  {
    "subject": "role:admin",
    "resource": "invoice",
    "action": "edit"
  },
  {
    "subject": "role:accountant",
    "resource": "invoice",
    "action": "view"
  },
  {
    "subject": "user:42",
    "resource": "invoice:123",
    "action": "delete",
    "condition": "owner"
  }
]
```

### Line-Based DSL Format

```
# RBAC Rules
role:admin, invoice, create
role:admin, invoice, edit
role:accountant, invoice, view

# Ownership-based rule
user:42, invoice:123, delete, owner
```

---

## Tips and Best Practices

1. **Always validate before importing**: Use `--dry-run` or `validate` command
2. **Backup before changes**: Export current policies before importing new ones
3. **Use version control**: Track DSL files in Git for change history
4. **Test in staging**: Import and test policies in staging environment first
5. **Automate validation**: Add validation to CI/CD pipeline
6. **Document policies**: Add comments to DSL files explaining complex rules
7. **Regular exports**: Schedule regular policy exports for backup and audit
8. **Monitor statistics**: Use `stats` command to track policy growth
9. **Use filters**: Use filters in `list` and `export` for large policy sets
10. **Clear caches after changes**: Always clear caches after policy updates

---

## Support

For issues, feature requests, or questions:
- GitHub: https://github.com/authza/authza/issues
- Documentation: https://docs.authza.dev

---

## License

MIT License - see LICENSE file for details
