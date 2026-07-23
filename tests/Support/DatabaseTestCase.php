<?php

declare(strict_types=1);

namespace MediasIndex\Tests\Support;

use MediasIndex\Indexer\MediaType;
use MediasIndex\Storage\Dialect;
use MediasIndex\Storage\Migrator;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

/**
 * Base for tests that need a real database.
 *
 * Skips when MEDIAS_INDEX_TEST_DSN is unset so the suite still passes without
 * one; compose.yml and the CI workflow both set it, so it does run where it
 * matters. Migrations are applied once, then every test starts from empty
 * tables — testing against the real schema rather than a hand-written copy is
 * the entire point.
 */
abstract class DatabaseTestCase extends TestCase
{
    protected PDO $pdo;

    protected function setUp(): void
    {
        $dsn = getenv('MEDIAS_INDEX_TEST_DSN');

        if ($dsn === false || $dsn === '') {
            self::markTestSkipped('MEDIAS_INDEX_TEST_DSN is not set.');
        }

        try {
            $this->pdo = new PDO(
                $dsn,
                getenv('MEDIAS_INDEX_TEST_USER') ?: null,
                getenv('MEDIAS_INDEX_TEST_PASSWORD') ?: null,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ],
            );
        } catch (PDOException $e) {
            self::markTestSkipped('Test database unreachable: ' . $e->getMessage());
        }

        (new Migrator(
            $this->pdo,
            Dialect::forDriver((string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)),
            dirname(__DIR__, 2) . '/db/migrations',
        ))->migrate();

        // Children first — the foreign keys would otherwise refuse.
        foreach (['medias', 'projects', 'clients', 'scans'] as $table) {
            $this->pdo->exec('DELETE FROM ' . $table);
        }
    }

    /** Inserts a client, project and media chain and returns their ids. */
    protected function seedMedia(
        string $client,
        string $project,
        string $media,
        int $sizeBytes = 0,
        string $type = MediaType::HTML,
        ?string $entryPath = 'index.html',
        int $mtime = 0,
    ): int {
        $now = time();

        $clientId = $this->id(
            'SELECT id FROM clients WHERE slug = ?',
            [$client],
            'INSERT INTO clients (slug, name, first_seen_at, last_seen_at) VALUES (?, ?, ?, ?)',
            [$client, $client, $now, $now],
        );

        $projectId = $this->id(
            'SELECT id FROM projects WHERE client_id = ? AND slug = ?',
            [$clientId, $project],
            'INSERT INTO projects (client_id, slug, name, ctime, first_seen_at, last_seen_at)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$clientId, $project, $project, $now, $now, $now],
        );

        $insert = $this->pdo->prepare(
            'INSERT INTO medias (project_id, slug, name, type, entry_path, size_bytes, mtime,
                                 search_text, first_seen_at, last_seen_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        );
        $insert->execute([$projectId, $media, $media, $type, $entryPath, $sizeBytes, $mtime, $media, $now, $now]);

        return $projectId;
    }

    /** @param list<mixed> $selectParams @param list<mixed> $insertParams */
    private function id(string $select, array $selectParams, string $insert, array $insertParams): int
    {
        $statement = $this->pdo->prepare($select);
        $statement->execute($selectParams);
        $id = $statement->fetchColumn();

        if ($id !== false) {
            return (int) $id;
        }

        $this->pdo->prepare($insert)->execute($insertParams);
        $statement->execute($selectParams);

        return (int) $statement->fetchColumn();
    }
}
