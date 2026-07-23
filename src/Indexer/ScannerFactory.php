<?php

declare(strict_types=1);

namespace MediasIndex\Indexer;

use MediasIndex\Search\LikeSearch;
use MediasIndex\Storage\ClientRepository;
use MediasIndex\Storage\MediaRepository;
use MediasIndex\Storage\ProjectRepository;
use MediasIndex\Storage\ScanRepository;
use MediasIndex\Support\Config;
use PDO;

/**
 * Builds a Scanner from configuration.
 *
 * Exists so the CLI entry point and the HTTP hook cannot drift into scanning
 * differently — a scan triggered from a browser has to produce exactly what the
 * cron job would.
 */
final class ScannerFactory
{
    public static function create(Config $config, PDO $pdo): Scanner
    {
        return new Scanner(
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
    }
}
