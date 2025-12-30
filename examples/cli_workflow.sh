#!/bin/bash
# Example: Complete workflow using CLI

set -e

echo "=== Authza CLI Workflow Example ==="

# Step 1: Validate DSL
echo "Step 1: Validating policies..."
./bin/authz validate --file=examples/dsl/rbac_rules.json

# Step 2: Import policies
echo "Step 2: Importing policies..."
./bin/authz import --file=examples/dsl/rbac_rules.json

# Step 3: Build permission graph
echo "Step 3: Building permission graph..."
./bin/authz graph:build

# Step 4: Test permissions
echo "Step 4: Testing permissions..."
./bin/authz check --user=1 --resource=invoice:123 --action=edit --roles=admin

# Step 5: List all policies
echo "Step 5: Listing policies..."
./bin/authz list --format=table

# Step 6: Export for backup
echo "Step 6: Exporting policies..."
./bin/authz export --output=backup.json --format=json

echo "=== Workflow completed successfully ==="
