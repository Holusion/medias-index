<?php

declare(strict_types=1);

namespace MediasIndex\Storage;

use PDO;

/**
 * The audit trail for scans.
 *
 * Worth having from the start: on OVH the cron runs unattended at an hour the
 * host picks, and the POST hook is fired by something else entirely, so without
 * this there is no way to tell whether indexing ran, how long it took, or why it
 * stopped.
 */
final class ScanRepository
{
    public const RUNNING = 'running';
    public const OK = 'ok';
    public const FAILED = 'failed';

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function open(string $triggeredBy, string $scope, int $startedAt): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO scans (triggered_by, scope, status, started_at) VALUES (?, ?, ?, ?)',
        );
        $statement->execute([$triggeredBy, $scope, self::RUNNING, $startedAt]);

        $find = $this->pdo->prepare(
            'SELECT id FROM scans WHERE started_at = ? AND triggered_by = ? ORDER BY id DESC LIMIT 1',
        );
        $find->execute([$startedAt, $triggeredBy]);

        return (int) $find->fetchColumn();
    }

    /** @param array<string, int|string> $stats */
    public function close(int $scanId, string $status, array $stats, ?string $error = null): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE scans SET status = ?, finished_at = ?, stats = ?, error = ? WHERE id = ?',
        );
        $statement->execute([
            $status,
            time(),
            json_encode($stats, JSON_UNESCAPED_UNICODE),
            $error,
            $scanId,
        ]);
    }

    /** @return array<string, mixed>|null */
    public function latest(): ?array
    {
        $row = $this->pdo->query('SELECT * FROM scans ORDER BY id DESC LIMIT 1')?->fetch();

        return $row === false ? null : $row;
    }
}
