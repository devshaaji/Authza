## **10. DSL (Domain-Specific Language) Support**

To allow **non-PHP developers or administrators** to define policies, the engine supports a **DSL-based policy format**. These rules can be **imported into the engine** and evaluated alongside class-based policies, without changing the core system.

### **10.1 Purpose**

- Allow multi-language or non-PHP users to define rules.
- Maintain a **single source of truth** for policies.
- Enable **future microservices or cross-system enforcement**.
- Seamlessly integrate into the `PermissionGraph`.

---

### **10.2 Supported DSL Formats**

#### **Line-Based DSL**

subject, resource, action, optional condition

role:admin, invoice, create role:accountant, invoice, view user:42, invoice:123, delete, owner

- `role:ROLE_NAME` → all users with the role.  
- `user:USER_ID` → a specific user.  
- `resource` → resource type or specific instance (`invoice` or `invoice:123`).  
- `action` → `create`, `edit`, `view`, `delete`.  
- `condition` → optional, e.g., `owner`, `department==finance`.

#### **JSON DSL**

```json
[
  { "subject": "role:admin", "resource": "invoice", "action": "create" },
  { "subject": "role:accountant", "resource": "invoice", "action": "view" },
  { "subject": "user:42", "resource": "invoice:123", "action": "delete", "condition": "owner" }
]

Preferred for programmatic import/export.

Can be versioned in Git, DB, or config files.



---

10.3 Example Use Cases

RBAC Example

role:admin, invoice, create
role:admin, invoice, edit
role:accountant, invoice, view

Ownership Example

user:*, invoice, edit, owner

Context-Aware Example

role:manager, invoice, approve, department==finance


---

10.4 Workflow

Non-PHP User/Admin writes DSL → DSL Parser → PermissionGraph → Authorization Engine → Decision

PHP class-based policies and DSL rules coexist in the PermissionGraph.

authorize() and can() work identically, regardless of the rule source.

Precompute, caching, and logging all operate seamlessly.



---

10.5 Guidelines for DSL Authors

1. Start with simple RBAC rules.


2. Use owner and context fields for ABAC rules.


3. Maintain consistent role, action, and resource naming.


4. Optionally use wildcards (user:*, invoice:*) for broader rules.


5. Validate the DSL with provided CLI or script tools before importing.




---

10.6 Benefits

Human-readable and easy to audit.

Multi-language compatible for future microservices.

Allows non-developers to safely define policies without PHP code.

Fully integrates with caching, precompute, and logging.
