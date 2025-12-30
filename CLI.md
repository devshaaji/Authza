## **11. CLI Tools**

The authorization engine provides **CLI tools** to manage policies, import/export DSL rules, precompute permission graphs, clear caches, and test permissions. These tools help both **PHP developers and non-PHP users** efficiently operate the system.

---

### **11.1 Purpose**

- Manage and validate **DSL or class-based policies**.  
- Precompute **permission graphs** and manage cache.  
- Debug and **simulate policy decisions** without touching the app.  
- Audit and export policy logs for compliance.  

---

### **11.2 Common Commands**

```bash
# Import DSL rules
authz-cli import --file=policy_rules.json

# Export policies to DSL
authz-cli export --format=json --output=exported_rules.json

# Validate DSL rules
authz-cli validate --file=policy_rules.yaml

# Precompute or rebuild the permission graph
authz-cli graph:build

# Clear all caches
authz-cli cache:clear

# Check if a user can perform an action
authz-cli check --user=42 --resource=invoice:123 --action=edit

# List all policies
authz-cli list --filter=role:admin

# Dry-run to simulate changes without applying
authz-cli import --file=policy_rules.json --dry-run


---

11.3 Features & Benefits

1. Developer-friendly

Integrates with IDEs and pipelines for testing and validation.



2. Non-PHP user support

DSL rules can be imported, exported, or validated from CLI.



3. Operational management

Rebuild precomputed graphs, clear caches, and manage policies efficiently.



4. Audit & debug

Quickly check permission decisions or list policies.



5. Automation ready

CLI commands can be integrated into CI/CD pipelines for safe policy deployment.





---

11.4 Implementation Tips

Use a console framework (e.g., Symfony Console) for structured commands.

Include verbose, dry-run, and environment flags for safety.

Keep commands idempotent: running them multiple times should not break the system.

Allow integration with logs and caching adapters for full system visibility.



---

11.5 Example Workflow

# Admin writes DSL rules
authz-cli validate --file=new_rules.json
authz-cli import --file=new_rules.json

# Rebuild permission graph for fast evaluation
authz-cli graph:build

# Developer tests permissions
authz-cli check --user=42 --resource=invoice:123 --action=edit

# Export policies for audit or microservice consumption
authz-cli export --format=json --output=exported_rules.json
