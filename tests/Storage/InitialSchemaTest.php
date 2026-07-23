<?php

declare(strict_types=1);

namespace MediasIndex\Tests\Storage;

use MediasIndex\Storage\Dialect;
use MediasIndex\Storage\Migration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Guards the portability rules from docs/DESIGN.md §6 without needing a
 * database. Migrations return their statements precisely so this is possible.
 */
final class InitialSchemaTest extends TestCase
{
    private function migration(): Migration
    {
        return require dirname(__DIR__, 2) . '/db/migrations/001_initial_schema.php';
    }

    /** @return list<string> */
    private function sql(string $driver): array
    {
        return $this->migration()->statements(Dialect::forDriver($driver));
    }

    public function testBothDialectsProduceTheSameTablesAndIndexes(): void
    {
        $mysql = $this->sql(Dialect::MYSQL);
        $postgres = $this->sql(Dialect::POSTGRESQL);

        self::assertCount(count($mysql), $postgres, 'the two dialects must build the same schema');

        foreach (['clients', 'projects', 'medias', 'scans'] as $table) {
            self::assertStringContainsString("CREATE TABLE {$table} (", implode("\n", $mysql));
            self::assertStringContainsString("CREATE TABLE {$table} (", implode("\n", $postgres));
        }
    }

    public function testIdentityColumnsUseTheDialectSpelling(): void
    {
        self::assertStringContainsString('AUTO_INCREMENT', implode("\n", $this->sql(Dialect::MYSQL)));
        self::assertStringContainsString(
            'GENERATED ALWAYS AS IDENTITY',
            implode("\n", $this->sql(Dialect::POSTGRESQL)),
        );
    }

    public function testPostgresqlGetsNoMysqlTableOptions(): void
    {
        $postgres = implode("\n", $this->sql(Dialect::POSTGRESQL));

        self::assertStringNotContainsString('ENGINE=', $postgres);
        self::assertStringNotContainsString('CHARSET', $postgres);
    }

    #[DataProvider('drivers')]
    public function testAvoidsTypesThatDoNotPortBetweenEngines(string $driver): void
    {
        $sql = implode("\n", $this->sql($driver));

        foreach (['DATETIME', 'TIMESTAMP', 'ENUM(', ' JSON', 'UNSIGNED'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $sql, $forbidden . ' does not port cleanly');
        }
    }

    /**
     * PostgreSQL rejects index definitions inside CREATE TABLE, so they have to
     * be separate statements — and named, because their names are unique across
     * the whole schema there.
     */
    #[DataProvider('drivers')]
    public function testIndexesAreSeparateNamedStatements(string $driver): void
    {
        $statements = $this->sql($driver);
        $indexes = array_filter($statements, static fn (string $s): bool => str_starts_with($s, 'CREATE INDEX'));

        self::assertNotEmpty($indexes);

        foreach ($statements as $statement) {
            if (str_starts_with($statement, 'CREATE TABLE')) {
                self::assertDoesNotMatchRegularExpression(
                    '/^\s*(INDEX|KEY)\s/mi',
                    $statement,
                    'inline index definitions are MySQL-only',
                );
            }
        }

        foreach ($indexes as $index) {
            self::assertMatchesRegularExpression('/^CREATE INDEX idx_\w+ ON /', $index);
        }
    }

    /**
     * Sizes and counts live only on medias; anything above is aggregated at
     * query time so it cannot go stale.
     */
    public function testOnlyMediasCarriesMeasurements(): void
    {
        $statements = $this->sql(Dialect::MYSQL);
        $byTable = [];

        foreach ($statements as $statement) {
            if (preg_match('/^CREATE TABLE (\w+) \((.*)\)/s', $statement, $m) === 1) {
                $byTable[$m[1]] = $m[2];
            }
        }

        foreach (['size_bytes', 'file_count', 'mtime'] as $column) {
            self::assertStringContainsString($column, $byTable['medias']);
            self::assertStringNotContainsString($column, $byTable['clients']);
            self::assertStringNotContainsString($column, $byTable['projects']);
        }
    }

    /** "trigger" is reserved in both engines and would need quoting. */
    public function testScansAvoidsTheReservedTriggerColumnName(): void
    {
        $sql = implode("\n", $this->sql(Dialect::MYSQL));

        self::assertStringContainsString('triggered_by', $sql);
        self::assertDoesNotMatchRegularExpression('/^\s*trigger\s/mi', $sql);
    }

    public static function drivers(): array
    {
        return [Dialect::MYSQL => [Dialect::MYSQL], Dialect::POSTGRESQL => [Dialect::POSTGRESQL]];
    }
}
