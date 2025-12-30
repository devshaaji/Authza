<?php

return [
    'cache' => [
        'adapter' => 'file',
        'path' => __DIR__ . '/../cache'
    ],
    'graph' => [
        'storage' => __DIR__ . '/../storage/graph.json'
    ],
    'policies' => [
        'namespace' => 'App\\Policies',
        'path' => __DIR__ . '/../src/Policies'
    ]
];
