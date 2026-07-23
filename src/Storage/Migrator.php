<?php

declare(strict_types=1);

namespace MediasIndex\Storage;

use PDO;
use RuntimeException;

/**
 * Applies the numbered migrations in db/migrations, tracking what has run in
 * schema_migrations. No framework: migrations only ever go forward, and at this
 * size a down() would be more dangerous than useful — reverting means writing
 * the next migration.
 */
final class Migrator
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly Dialect $dialect,
        private readonly string $migrationsDir,
    ) {
    }

    /** @return list<string> versions not yet applied, in order */
    public function pending(): array
    {
        $this->createTrackingTable();
        $applied = $this->applied();

        return array_values(array_filter(
            array_keys($this->available()),
            static fn (string $version): bool => !in_array($version, $applied, true),
        ));
    }

    /**
     * Applies every pending migration.
     *
     * @return list<string> versions applied by this call
     */
    public function migrate(): array
    {
        $available = $this->available();
        $applied = [];

        foreach ($this->pending() as $version) {
            $migration = $this->load($available[$version]);
            $statements = $migration->statements($this->dialect);

            // Wrapped only where it means something. On MySQL the first DDL
            // statement commits implicitly and ends the transaction, so a
            // commit() afterwards fails with "no active transaction" — reporting
            // failure for a migration that actually applied.
            $transactional = $this->dialect->hasTransactionalDdl();

            if ($transactional) {
                $this->pdo->beginTransaction();
            }

            try {
                foreach ($statements as $statement) {
                    $this->pdo->exec($statement);
                }

                $record = $this->pdo->prepare(
                    'INSERT INTO schema_migrations (version, applied_at) VALUES (?, ?)',
                );
                $record->execute([$version, time()]);

                if ($transactional) {
                    $this->pdo->commit();
                }
            } catch (\Throwable $e) {
                if ($transactional && $this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }

                throw new RuntimeException(
                    sprintf(
                        'Migration "%s" failed: %s%s',
                        $version,
                        $e->getMessage(),
                        $this->dialect->hasTransactionalDdl()
                            ? ''
                            : ' — this engine commits DDL implicitly, so the schema may be'
                                . ' partially applied and needs repairing by hand.',
                    ),
                    0,
                    $e,
                );
            }

            $applied[] = $version;
        }

        return $applied;
    }

    /** @return list<string> */
    public function applied(): array
    {
        $this->createTrackingTable();

        $rows = $this->pdo->query('SELECT version FROM schema_migrations ORDER BY version')
            ?->fetchAll(PDO::FETCH_COLUMN) ?: [];

        return array_map(strval(...), $rows);
    }

    /** @return array<string, string> version => absolute file path, sorted by version */
    private function available(): array
    {
        $files = glob($this->migrationsDir . '/*.php') ?: [];
        $migrations = [];

        foreach ($files as $file) {
            $migrations[basename($file, '.php')] = $file;
        }

        ksort($migrations);

        return $migrations;
    }

    private function load(string $file): Migration
    {
        $migration = require $file;

        if (!$migration instanceof Migration) {
            throw new RuntimeException(sprintf('Migration "%s" must return a %s.', $file, Migration::class));
        }

        return $migration;
    }

    private function createTrackingTable(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations ('
            . 'version VARCHAR(64) NOT NULL PRIMARY KEY, '
            . 'applied_at BIGINT NOT NULL'
            . ')' . $this->dialect->tableSuffix(),
        );
    }
}
