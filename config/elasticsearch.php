<?php

return [
    // Enable/disable Elasticsearch (set to false for minimal setup)
    'enabled' => env('ELASTICSEARCH_ENABLED', false),

    // Elasticsearch connection
    'hosts' => [
        [
            'host' => env('ELASTICSEARCH_HOST', '127.0.0.1'),
            'port' => env('ELASTICSEARCH_PORT', 9200),
            'scheme' => env('ELASTICSEARCH_SCHEME', 'http'),
            'user' => env('ELASTICSEARCH_USERNAME'),
            'pass' => env('ELASTICSEARCH_PASSWORD'),
        ],
    ],

    // Index names
    'indices' => [
        'posts' => env('ELASTICSEARCH_INDEX_POSTS', 'posts'),
        'categories' => env('ELASTICSEARCH_INDEX_CATEGORIES', 'categories'),
        'tags' => env('ELASTICSEARCH_INDEX_TAGS', 'tags'),
    ],

    // Index settings
    'settings' => [
        'number_of_shards' => 1,
        'number_of_replicas' => 0,
    ],
];
