<?php

declare(strict_types=1);

namespace MediasIndex\Support;

use PDO;

/**
 * PDO connection factory.
 *
 * Shared hosting allows only 30 concurrent connections to the database, so a
 * connection is opened lazily, once per process, and closed with it.
 *
 * Everything above this class must stick to portable SQL — the DSN is meant to
 * be the only thing that changes when moving to PostgreSQL.
 */
final class Database
{
    private ?PDO $pdo = null;

    public function __construct(private readonly Config $config)
    {
    }

    public function pdo(): PDO
    {
        return $this->pdo ??= new PDO(
            $this->config->string('db.dsn'),
            $this->config->string('db.user'),
            $this->config->string('db.password'),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // Real prepared statements: portable behaviour and correct
                // typing of the BIGINT timestamps used everywhere.
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
            ],
        );
    }

    /** Driver name, e.g. "mysql" or "pgsql". Used only by the DDL dialect layer. */
    public function driver(): string
    {
        return (string) $this->pdo()->getAttribute(PDO::ATTR_DRIVER_NAME);
    }
}
