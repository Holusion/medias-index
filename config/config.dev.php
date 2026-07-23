<?php

/**
 * Configuration for the docker development environment only.
 * Selected through the MEDIAS_INDEX_CONFIG environment variable.
 */

declare(strict_types=1);

return [
    'db' => [
        'dsn' => 'mysql:host=db;dbname=medias_index;charset=utf8mb4',
        'user' => 'medias',
        'password' => 'medias',
    ],

    'paths' => [
        'files' => '/var/www/html/files',
        'thumbs' => '/var/www/html/thumbs',
    ],

    'urls' => [
        'origin' => 'http://localhost:8080',
        'app' => '',
        'files' => '/files',
        'thumbs' => '/thumbs',
    ],

    'hook' => [
        'token' => 'dev-token',
    ],

    'thumbnails' => [
        'width' => 400,
        'height' => 300,
        'quality' => 80,
    ],

    'scan' => [
        'ignore' => ['node_modules', 'lost+found'],
    ],

    'embed' => [
        'width' => 800,
        'height' => 600,
    ],
];
