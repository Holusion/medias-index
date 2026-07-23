<?php

declare(strict_types=1);

namespace MediasIndex\Tests\Support;

use MediasIndex\Support\Config;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ConfigTest extends TestCase
{
    public function testReadsNestedKeysWithDottedNotation(): void
    {
        $config = new Config(['db' => ['dsn' => 'mysql:host=localhost']]);

        self::assertSame('mysql:host=localhost', $config->get('db.dsn'));
    }

    public function testMissingMandatoryKeyThrows(): void
    {
        $config = new Config([]);

        $this->expectException(RuntimeException::class);
        $config->get('db.dsn');
    }

    public function testMissingOptionalKeyFallsBackToDefault(): void
    {
        $config = new Config([]);

        self::assertSame(400, $config->int('thumbnails.width', 400));
    }

    public function testPathAndUrlDropTrailingSlashes(): void
    {
        $config = new Config([
            'paths' => ['files' => '/var/www/html/files/'],
            'urls' => ['files' => '/files/'],
        ]);

        self::assertSame('/var/www/html/files', $config->path('paths.files'));
        self::assertSame('/files', $config->url('urls.files'));
    }

    public function testCandidatePathsAreOrderedFromSafestToLeastSafe(): void
    {
        $app = Config::rootDir();
        $www = dirname($app);
        $home = dirname($www);

        // The env-var override is only present when set, so compare the tail.
        $candidates = Config::candidatePaths();

        self::assertSame(
            [
                $home . '/config/medias-index.php',
                $www . '/config.php',
                $app . '/config/config.php',
            ],
            array_slice($candidates, -3),
        );
    }

    public function testLoadRecordsWhereTheConfigCameFrom(): void
    {
        $path = Config::rootDir() . '/config/config.example.php';

        self::assertSame($path, Config::load($path)->sourcePath());
    }

    public function testExampleConfigIsLoadableAndComplete(): void
    {
        $config = Config::load(Config::rootDir() . '/config/config.example.php');

        foreach (['db.dsn', 'paths.files', 'paths.thumbs', 'urls.origin', 'hook.token'] as $key) {
            self::assertNotSame('', (string) $config->get($key), $key . ' must be present');
        }
    }
}
