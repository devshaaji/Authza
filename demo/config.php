<?php

declare(strict_types=1);

/**
 * Demo Application Configuration
 * 
 * This config demonstrates how to set up Authza using the factory pattern.
 */

use Authza\Adapters\Cache\FileCache;

return [
    /**
     * Cache Configuration
     * Using ArrayCache for demo (in-memory, no persistence needed)
     */
    'cache' => [
        'instance' => new FileCache(__DIR__ . '/var/cache'),
    ],

    /**
     * Permission Graph Storage
     */
    'graph' => [
        'storage' => __DIR__ . '/var/graph.json',
    ],

    /**
     * PHP Policy Classes
     */
    'policies' => [
        'register' => [
            'invoice' => \Authza\Policies\InvoicePolicy::class,
            'document' => \Demo\Policies\DocumentPolicy::class,
            'project' => \Demo\Policies\ProjectPolicy::class,
        ],
    ],

    /**
     * DSL Policy Files
     */
    'dsl' => [
        'files' => [
            __DIR__ . '/policies/base_rules.json',
        ],
    ],
];
