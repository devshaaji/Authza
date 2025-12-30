# Authza
# PHP Authorization Engine (Vendor Project)

A modern, plug-and-play **authorization engine for PHP** designed to be **scalable, framework-agnostic, and developer-friendly**. This library fills the gaps left by existing solutions like Casbin and Laravel Gates by providing **precomputed permission graphs, PSR-compliant caching and logging, class-based context-aware policies, and quickStart defaults**.

---

## **Table of Contents**

- [Project Goal](#project-goal)  
- [Features](#features)  
- [System Architecture](#system-architecture)  
- [System Design](#system-design)  
- [Limitations](#limitations)  
- [Todo List](#todo-list)  
- [Quick Start](#quick-start)  

---

## **Project Goal**

Our goal is to create a **vendor-ready PHP authorization engine** that:

1. Works **out-of-the-box** for common use cases with minimal setup.  
2. Supports **class-based, context-aware, hybrid policies** (RBAC + ABAC + ReBAC).  
3. Uses **precomputed permission graphs** for high scalability.  
4. Accepts **PSR-16 caches** and **PSR-3 loggers** for flexible integration.  
5. Provides **quickStart defaults** for developers, while allowing advanced customization.  
6. Includes optional **adapters** for caching, persistence, and popular frameworks.  

---

## **Features**

- `authorize()` and `can()` methods for policy evaluation  
- Class-based policies per resource (e.g., `InvoicePolicy`)  
- Hybrid ABAC + RBAC + relationship-based checks  
- Precomputed **PermissionGraph** for fast decision lookups  
- PSR-16 cache integration for decisions and graph caching  
- PSR-3 compliant logging for audit and debugging  
- Auto-discovery of policies for quick setup  
- Optional adapters for:
  - Cache: Redis, APCu, File, Array  
  - Persistence: PDO, JSON  
  - Framework integration: Laravel, Slim, Symfony  
- Developer-friendly unit testing helpers  
- Multi-layer cache support (APCu → Redis → DB fallback)

---

## **System Architecture**

```text
Authorization Engine
 ├── Core (framework-agnostic)
 │    ├── Authorization.php        # Entry point
 │    ├── PolicyRegistry.php       # Maps resources → policies
 │    ├── Graph/PermissionGraph.php # Precomputed graph
 │    ├── Interfaces/
 │    │     ├── SubjectInterface.php
 │    │     ├── ResourceInterface.php
 │    │     └── PolicyInterface.php
 │    └── Exceptions/
 │          └── AuthorizationException.php
 │
 ├── Adapters (optional plug-ins)
 │    ├── Cache/
 │    │     ├── RedisCache.php
 │    │     ├── FileCache.php
 │    │     └── ArrayCache.php
 │    ├── Persistence/
 │    │     ├── PdoPolicyStore.php
 │    │     ├── PdoPermissionStore.php
 │    │     └── JsonPolicyStore.php
 │    └── Framework/
 │          ├── LaravelServiceProvider.php
 │          ├── SlimMiddleware.php
 │          └── SymfonyListener.php
 │
 ├── Policies/                     # Example policies for quickStart
 │    ├── UserPolicy.php
 │    ├── InvoicePolicy.php
 │    └── ClientPolicy.php
 └── Tests/                         # Unit and integration tests


---

System Design

Flow of authorize() / can()

1. PermissionGraph lookup: checks if the decision exists in cache/graph.


2. Policy resolution: fetches the policy class via PolicyRegistry.


3. Policy evaluation: runs method for action (edit, view, delete) with SubjectInterface, ResourceInterface, and optional context.


4. Caching: stores decision in PSR-16 cache for future requests.


5. Logging: logs authorization decisions, cache hits/misses, and context via PSR-3 logger.


6. Result: can() returns boolean; authorize() throws AuthorizationException if denied.



Key Components

Authorization: Main interface for developers

PolicyRegistry: Maps resources to policies, supports auto-discovery

PermissionGraph: Precomputes user-role-permission-resource relationships for high performance

Adapters: Optional plug-ins for caching, persistence, and framework integration

Policies: Class-based, context-aware rules

Logging: PSR-3 compliant, fully pluggable

Caching: PSR-16 compliant, supports multiple adapters and layers



---

Limitations

QuickStart provides defaults for common use cases only; complex enterprise logic may require custom policies or adapters.

Precomputed graphs need refresh/invalidation logic in dynamic environments.

High-concurrency setups may require multi-layer caching tuning.

QuickStart is framework-agnostic, but deep integration (middleware, service providers) requires adapter usage.



---

Todo List

[ ] Implement core Authorization engine

[ ] Implement PolicyRegistry and auto-discovery

[ ] Build PermissionGraph with caching support

[ ] Implement PSR-16 compliant cache adapters

[ ] Implement PSR-3 compliant logging support

[ ] Provide example policies (InvoicePolicy, UserPolicy)

[ ] Create quickStart() API

[ ] Build framework adapters (Laravel, Slim, Symfony)

[ ] Add unit testing helpers and example tests

[ ] Document caching TTL, graph refresh, and logging configuration

[ ] Add multi-layer caching support for production scalability



---

Quick Start Example

use Authz\Authorization;
use Authz\Adapters\Cache\RedisCache;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Authz\Adapters\Persistence\PdoPolicyStore;

$cache = new RedisCache($redis);
$logger = new Logger('authz');
$logger->pushHandler(new StreamHandler(__DIR__.'/auth.log'));
$policyStore = new PdoPolicyStore($pdo);

$authz = Authorization::quickStart([
    'cache' => $cache,
    'logger' => $logger,
    'policyStore' => $policyStore,
    'policyNamespace' => 'App\\Policies'
]);

$user = new App\Models\User();
$invoice = new App\Models\Invoice();

if ($authz->can($user, 'edit', $invoice)) {
    echo "User can edit invoice.";
} else {
    echo "Access denied.";
}


---

Conclusion

This project aims to fill the gaps in existing PHP authorization engines by combining:

PSR standards compliance (cache & logger)

Precomputed permission graphs for scalability

Class-based, context-aware policies

Plug-and-play adapters and quickStart defaults


It provides a modern, production-ready foundation for PHP applications requiring flexible, scalable, and developer-friendly authorization.
