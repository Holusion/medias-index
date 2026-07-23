<?php

/**
 * Template for the local configuration. It holds database credentials and the
 * hook secret, so it is never committed and never uploaded by the deployment.
 *
 * Copy it to the FIRST of these locations your host allows — src/Support/Config.php
 * tries them in this order and bin/doctor.php reports which one was used:
 *
 *   1. <home>/config/medias-index.php   outside the document root — preferred
 *   2. <home>/www/config.php            inside it, denied in www/.htaccess
 *   3. <app>/config/config.php          local development only
 *
 * Paths below are absolute on purpose: this file must work from any of those
 * locations, so nothing is derived from __DIR__. Replace /home/CHANGE_ME with
 * the account home shown by `pwd` over SSH.
 */

declare(strict_types=1);

$www = '/home/CHANGE_ME/www';

return [
    'db' => [
        // Generic SQL is used throughout; only this DSN should need to change
        // to move to PostgreSQL (plus running the migrations on the new engine).
        'dsn' => 'mysql:host=localhost;dbname=medias_index;charset=utf8mb4',
        'user' => 'CHANGE_ME',
        'password' => 'CHANGE_ME',
    ],

    'paths' => [
        'files' => $www . '/files',
        'thumbs' => $www . '/thumbs',
    ],

    'urls' => [
        // Absolute origin, used to build copy-pasteable links and iframe
        // snippets. No trailing slash.
        'origin' => 'https://example.com',
        // Path prefixes below the origin.
        'app' => '',
        'files' => '/files',
        'thumbs' => '/thumbs',
    ],

    'hook' => [
        // Shared secret for POST /hook/scan. Generate with:
        //   php -r 'echo bin2hex(random_bytes(24)), PHP_EOL;'
        'token' => 'CHANGE_ME',
    ],

    'thumbnails' => [
        'width' => 400,
        'height' => 300,
        'quality' => 80,
    ],

    'scan' => [
        // Directory names skipped at every level, on top of the built-in rule
        // that anything starting with a dot is ignored.
        'ignore' => ['node_modules', 'lost+found'],
    ],

    'embed' => [
        'width' => 800,
        'height' => 600,
    ],
];
