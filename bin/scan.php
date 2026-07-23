<?php

/**
 * Indexes the content tree.
 *
 *   php bin/scan.php                       scan everything
 *   php bin/scan.php --client=acme         scan one client
 *   php bin/scan.php --client=acme --project=expo
 *
 * This is what the OVH scheduled task runs. Note that OVH shared hosting runs
 * cron hourly at best and picks the minute itself, so the POST hook is what
 * makes indexing feel immediate — not this.
 */

declare(strict_types=1);

use MediasIndex\Indexer\MediaInspector;
use MediasIndex\Indexer\Scanner;
use MediasIndex\Indexer\ScanScope;
use MediasIndex\Indexer\ThumbnailGenerator;
use MediasIndex\Indexer\Tree;
use MediasIndex\Search\LikeSearch;
use MediasIndex\Storage\ClientRepository;
use MediasIndex\Storage\MediaRepository;
use MediasIndex\Storage\ProjectRepository;
use MediasIndex\Storage\ScanRepository;
use MediasIndex\Support\Config;
use MediasIndex\Support\Database;

require dirname(__DIR__) . '/vendor/autoload.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$options = getopt('', ['client::', 'project::']);
$client = $options['client'] ?? null;
$project = $options['project'] ?? null;

if ($project !== null && $client === null) {
    fwrite(STDERR, '--project requires --client' . PHP_EOL);
    exit(1);
}

$scope = match (true) {
    $client === null => ScanScope::all(),
    $project === null => ScanScope::client((string) $client),
    default => ScanScope::project((string) $client, (string) $project),
};

try {
    $config = Config::load();
    $pdo = (new Database($config))->pdo();

    $scanner = new Scanner(
        new Tree($config->path('paths.files'), $config->array('scan.ignore')),
        new MediaInspector(),
        new ClientRepository($pdo),
        new ProjectRepository($pdo),
        new MediaRepository($pdo, new LikeSearch()),
        new ScanRepository($pdo),
        null,
        new ThumbnailGenerator(
            $config->path('paths.thumbs'),
            $config->int('thumbnails.width', 400),
            $config->int('thumbnails.height', 300),
            $config->int('thumbnails.quality', 80),
        ),
    );

    $result = $scanner->scan($scope, 'cli');

    printf(
        "scan #%d (%s): %d client(s), %d project(s), %d media(s), %d unusable — "
        . "%d/%d/%d removed — %ds%s",
        $result->scanId,
        $scope->describe(),
        $result->clients,
        $result->projects,
        $result->medias,
        $result->unusable,
        $result->deletedClients,
        $result->deletedProjects,
        $result->deletedMedias,
        $result->durationSeconds,
        PHP_EOL,
    );
} catch (Throwable $e) {
    fwrite(STDERR, 'Scan failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
