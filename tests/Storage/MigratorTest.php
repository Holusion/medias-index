<?php

declare(strict_types=1);

namespace MediasIndex\Tests\Storage;

use MediasIndex\Storage\Dialect;
use MediasIndex\Storage\Migrator;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

/**
 * Runs the real migrations against a real database.
 *
 * Skips itself when MEDIAS_INDEX_TEST_DSN is unset, so the suite still passes
 * on a machine without one. compose.yml sets it.
 */
final class MigratorTest extends TestCase
{
    private PDO $pdo;

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
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false],
            );
        } catch (PDOException $e) {
            self::markTestSkipped('Test database unreachable: ' . $e->getMessage());
        }

        // Children first: the foreign keys are ON DELETE CASCADE, not ON DROP.
        foreach (['medias', 'projects', 'clients', 'scans', 'schema_migrations'] as $table) {
            $this->pdo->exec('DROP TABLE IF EXISTS ' . $table);
        }
    }

    private function migrator(): Migrator
    {
        return new Migrator(
            $this->pdo,
            Dialect::forDriver((string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)),
            dirname(__DIR__, 2) . '/db/migrations',
        );
    }

    public function testAppliesEveryMigrationAndRecordsIt(): void
    {
        $migrator = $this->migrator();

        self::assertSame([], $migrator->applied());
        self::assertContains('001_initial_schema', $migrator->pending());

        $applied = $migrator->migrate();

        self::assertSame(['001_initial_schema'], $applied);
        self::assertSame(['001_initial_schema'], $migrator->applied());
        self::assertSame([], $migrator->pending());
    }

    /**
     * The failure this caught first time round: on MySQL the initial CREATE
     * TABLE commits implicitly, so a commit() afterwards throws even though the
     * migration applied — reporting failure for a schema that is actually there.
     */
    public function testMigratingIsIdempotent(): void
    {
        $this->migrator()->migrate();

        self::assertSame([], $this->migrator()->migrate());
        self::assertSame(['001_initial_schema'], $this->migrator()->applied());
    }

    public function testCreatesTheExpectedTables(): void
    {
        $this->migrator()->migrate();

        foreach (['clients', 'projects', 'medias', 'scans'] as $table) {
            // Throws on a missing table, ERRMODE_EXCEPTION being set.
            self::assertNotFalse(
                $this->pdo->query('SELECT * FROM ' . $table . ' WHERE 1 = 0'),
                $table . ' should exist',
            );
        }
    }

    public function testMediaSlugsAreUniquePerProjectButNotGlobally(): void
    {
        $this->migrator()->migrate();

        $now = time();
        $this->pdo->exec(
            "INSERT INTO clients (slug, name, first_seen_at, last_seen_at)
             VALUES ('c', 'c', {$now}, {$now})",
        );
        $client = (int) $this->pdo->lastInsertId();

        foreach (['p1', 'p2'] as $slug) {
            $this->pdo->exec(
                "INSERT INTO projects (client_id, slug, name, first_seen_at, last_seen_at)
                 VALUES ({$client}, '{$slug}', '{$slug}', {$now}, {$now})",
            );
        }

        $projects = $this->pdo->query('SELECT id FROM projects ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);

        // Same media slug under two different projects: allowed.
        foreach ($projects as $projectId) {
            $this->pdo->exec(
                "INSERT INTO medias (project_id, slug, name, first_seen_at, last_seen_at)
                 VALUES ({$projectId}, 'salle-1', 'salle-1', {$now}, {$now})",
            );
        }

        self::assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM medias')->fetchColumn());

        // Twice under the same project: rejected.
        $this->expectException(PDOException::class);
        $this->pdo->exec(
            "INSERT INTO medias (project_id, slug, name, first_seen_at, last_seen_at)
             VALUES ({$projects[0]}, 'salle-1', 'salle-1', {$now}, {$now})",
        );
    }

    public function testDeletingAClientCascadesToItsProjectsAndMedias(): void
    {
        $this->migrator()->migrate();

        $now = time();
        $this->pdo->exec(
            "INSERT INTO clients (slug, name, first_seen_at, last_seen_at) VALUES ('c', 'c', {$now}, {$now})",
        );
        $client = (int) $this->pdo->lastInsertId();
        $this->pdo->exec(
            "INSERT INTO projects (client_id, slug, name, first_seen_at, last_seen_at)
             VALUES ({$client}, 'p', 'p', {$now}, {$now})",
        );
        $project = (int) $this->pdo->lastInsertId();
        $this->pdo->exec(
            "INSERT INTO medias (project_id, slug, name, first_seen_at, last_seen_at)
             VALUES ({$project}, 'm', 'm', {$now}, {$now})",
        );

        $this->pdo->exec('DELETE FROM clients WHERE id = ' . $client);

        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM projects')->fetchColumn());
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM medias')->fetchColumn());
    }

    /** The aggregate the whole schema is shaped around. */
    public function testSizesAggregateFromMediasRatherThanBeingStored(): void
    {
        $this->migrator()->migrate();

        $now = time();
        $this->pdo->exec(
            "INSERT INTO clients (slug, name, first_seen_at, last_seen_at) VALUES ('c', 'c', {$now}, {$now})",
        );
        $client = (int) $this->pdo->lastInsertId();
        $this->pdo->exec(
            "INSERT INTO projects (client_id, slug, name, first_seen_at, last_seen_at)
             VALUES ({$client}, 'p', 'p', {$now}, {$now})",
        );
        $project = (int) $this->pdo->lastInsertId();

        $this->pdo->exec(
            "INSERT INTO medias (project_id, slug, name, size_bytes, first_seen_at, last_seen_at)
             VALUES ({$project}, 'a', 'a', 100, {$now}, {$now}),
                    ({$project}, 'b', 'b', 250, {$now}, {$now}),
                    ({$project}, 'gone', 'gone', 999, {$now}, {$now})",
        );
        $this->pdo->exec("UPDATE medias SET deleted_at = {$now} WHERE slug = 'gone'");

        $total = $this->pdo->query(
            'SELECT COALESCE(SUM(m.size_bytes), 0) FROM clients c
             JOIN projects p ON p.client_id = c.id
             JOIN medias m ON m.project_id = p.id
             WHERE m.deleted_at IS NULL',
        )->fetchColumn();

        // Soft-deleted rows drop out of the total for free — the reason the
        // aggregate is derived rather than stored.
        self::assertSame(350, (int) $total);
    }
}
