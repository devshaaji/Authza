<?php

/**
 * Authza Configuration File
 * 
 * This file configures the Authza authorization engine for both
 * CLI commands and application code.
 * 
 * SECURITY NOTES:
 * - All policies must be explicitly registered (no auto-discovery)
 * - All DSL files must be explicitly listed
 * - Use --config CLI option in production environments
 * 
 * @see https://github.com/authza/authza
 */

use Authza\Adapters\Cache\ArrayCache;
use Authza\Adapters\Cache\FileCache;

return [
    /**
     * Cache Configuration (PSR-16 SimpleCache) - Optional
     * 
     * If not provided, the system will evaluate policies directly without caching.
     * For production, providing a cache instance improves performance.
     * 
     * Options:
     * - Use Authza\Adapters\Cache\ArrayCache for testing/development
     * - Use Authza\Adapters\Cache\FileCache for simple file-based caching
     * - Use your own PSR-16 implementation (Redis, Memcached, etc.)
     */
    'cache' => [
        // 'instance' => new ArrayCache(),
        'instance' => new FileCache(__DIR__ . '/var/cache/authza'),
    ],

    /**
     * Logger Configuration (PSR-3 Logger) - Optional
     * 
     * Inject your own PSR-3 logger for audit logging.
     * If not provided, a NullLogger is used.
     */
    'logger' => [
        // 'instance' => new \Monolog\Logger('authza'),
    ],

    /**
     * Permission Graph Storage
     * 
     * Path to persist the computed permission graph.
     */
    'graph' => [
        'storage' => __DIR__ . '/var/storage/authza_graph.json',
    ],

    /**
     * PHP Policy Classes (Explicit Registration)
     * 
     * SECURITY: All policies must be explicitly registered.
     * Auto-discovery is disabled to prevent loading malicious code.
     * 
     * Format: 'resource_type' => PolicyClass::class
     *    or:  'resource_type' => new PolicyClass()
     */
    'policies' => [
        'register' => [
            // Example: Register your policy classes
            'user' => \Authza\Policies\UserPolicy::class,
            'invoice' => \Authza\Policies\InvoicePolicy::class,
            // 'document' => new \App\Policies\DocumentPolicy($someDependency),
        ],
    ],

    /**
     * DSL Policy Files (Explicit File List)
     * 
     * SECURITY: All DSL files must be explicitly listed.
     * Glob patterns are supported but must be explicitly configured.
     */
    'dsl' => [
        'files' => [
            // Example: List your DSL rule files
            // __DIR__ . '/rules/rbac.json',
            // __DIR__ . '/rules/permissions.dsl',
            // __DIR__ . '/rules/*.json',  // Glob pattern
        ],
    ],

    /**
     * Bootstrap Callback (Optional)
     * 
     * For advanced customization after the container is initialized.
     * Receives the ServiceContainer instance.
     */
    // 'bootstrap' => function(\Authza\Console\ServiceContainer $container) {
    //     // Custom initialization code
    // },
];