# Authza — PHP Authorization Engine

Authza is a **high-performance, framework-agnostic authorization engine for PHP**.

It evaluates access decisions using **precomputed permission graphs**, supports **PHP policies and DSL-based rules**, and lets applications define their own authorization model without imposed role structures.

Authza answers one question:

> **Can this subject perform this action on this resource right now?**

---

## Why Authza

* No framework lock-in
* No forced role or permission model
* No runtime rule parsing
* No hidden magic

Just **explicit policies**, **fast decisions**, and **full control**.

---

## Features

* `can()` and `authorize()` APIs
* PHP policy classes per resource
* Optional DSL (JSON or line-based)
* Hybrid RBAC + ABAC + relationship checks
* Precomputed **PermissionGraph** (cached)
* Explicit allow / deny with deterministic resolution
* PSR-16 cache and PSR-3 logging support
* CLI for policy import, export, validation, and graph management

---

## Core Ideas

### Subjects Are Opaque

Authza does not define roles, users, tenants, or permissions.

Examples like:

```
role:admin
user:42
tenant:23:billing
```

are **application-defined identifiers**.
Authza evaluates structure and wildcards — not semantics.

---

### Decisions Are Precomputed

Policies are compiled into a **PermissionGraph**:

```
subject : action : resource : resourceId → allow | deny
```

* The graph is cached
* Authorization checks are near constant-time
* Results are **not cached** to avoid stale decisions

---

### Context Comes From the App

Ownership, status, time, and custom conditions are evaluated by the application through condition resolvers.

Authza never guesses.

---

## Policy Sources

* PHP policy classes
* DSL files (JSON or line-based)
* Multiple sources can coexist safely
* All rules normalize into a single internal format

---

## CLI

Authza ships with a CLI for policy operations:

```bash
authz import
authz export
authz validate
authz graph:build
authz graph:invalidate
authz check
authz stats
authz cache:clear
```

Ideal for CI, audits, and environment sync.

---

## Installation

```bash
composer require authza/authza
```

---

## What Authza Is Not

* Not an authentication system
* Not a role manager
* Not framework permission glue
* Not a runtime policy interpreter

Authza is an **authorization engine**, nothing more.

---

## Documentation

* DSL: `docs/DSL.md`
* CLI: `docs/CLI.md`
* Architecture: `docs/ARCHITECTURE.md`

---

## License

MIT License — see [LICENSE](LICENSE)
