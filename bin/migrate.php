<?php

/**
 * Applies pending database migrations.
 *
 *   php bin/migrate.php              apply everything pending
 *   php bin/migrate.php --pending    list what would run, change nothing
 *   php bin/migrate.php --status     list applied and pending versions
 *
 * CLI only: it is a deployment step, not something to expose over HTTP.
 */

declare(strict_types=1);

use MediasIndex\Storage\Dialect;
use MediasIndex\Storage\Migrator;
use MediasIndex\Support\Config;
use MediasIndex\Support\Database;

require dirname(__DIR__) . '/vendor/autoload.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$config = Config::load();
$database = new Database($config);

try {
    $migrator = new Migrator(
        $database->pdo(),
        Dialect::forDriver($database->driver()),
        dirname(__DIR__) . '/db/migrations',
    );

    $pending = $migrator->pending();

    if (in_array('--status', $argv, true)) {
        foreach ($migrator->applied() as $version) {
            echo 'applied  ', $version, PHP_EOL;
        }

        foreach ($pending as $version) {
            echo 'pending  ', $version, PHP_EOL;
        }

        exit(0);
    }

    if ($pending === []) {
        echo 'Schema is up to date.', PHP_EOL;
        exit(0);
    }

    if (in_array('--pending', $argv, true)) {
        foreach ($pending as $version) {
            echo 'pending  ', $version, PHP_EOL;
        }

        exit(0);
    }

    foreach ($migrator->migrate() as $version) {
        echo 'applied  ', $version, PHP_EOL;
    }

    echo count($pending), ' migration(s) applied.', PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
