<?php
return [
    'cache' => [
        'adapter' => 'file',           // file, redis, apcu, array
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