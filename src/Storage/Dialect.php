<?php

declare(strict_types=1);

namespace MediasIndex\Storage;

use InvalidArgumentException;

/**
 * The small set of DDL spellings that genuinely differ between MySQL and
 * PostgreSQL.
 *
 * Everything else in the schema is written once, in portable SQL: BIGINT unix
 * timestamps rather than DATETIME, VARCHAR/TEXT rather than ENUM or JSON,
 * indexes as separate CREATE INDEX statements (MySQL accepts inline INDEX
 * definitions, PostgreSQL does not), and explicit index names because
 * PostgreSQL requires them to be unique across the schema.
 *
 * Keeping this class tiny is the point: if it starts growing, the schema has
 * stopped being portable.
 */
final class Dialect
{
    public const MYSQL = 'mysql';
    public const POSTGRESQL = 'pgsql';

    private function __construct(private readonly string $driver)
    {
    }

    public static function forDriver(string $driver): self
    {
        if (!in_array($driver, [self::MYSQL, self::POSTGRESQL], true)) {
            throw new InvalidArgumentException(sprintf('Unsupported database driver "%s".', $driver));
        }

        return new self($driver);
    }

    /** Auto-incrementing primary key column definition, minus the column name. */
    public function identityPrimaryKey(): string
    {
        return $this->driver === self::MYSQL
            ? 'BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY'
            : 'BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY';
    }

    /** Trailing clause of a CREATE TABLE statement. */
    public function tableSuffix(): string
    {
        return $this->driver === self::MYSQL
            ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            : '';
    }

    /**
     * Whether DDL participates in transactions.
     *
     * PostgreSQL rolls failed DDL back; MySQL commits implicitly on every
     * CREATE TABLE, so a migration that fails halfway leaves the schema
     * partially applied and has to be repaired by hand. The Migrator warns
     * about this rather than pretending otherwise.
     */
    public function hasTransactionalDdl(): bool
    {
        return $this->driver === self::POSTGRESQL;
    }
}
