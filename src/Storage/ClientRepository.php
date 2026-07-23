<?php

declare(strict_types=1);

namespace MediasIndex\Storage;

use PDO;

/**
 * Clients: the admin index query, plus what the scanner needs to keep the table
 * in step with the disk.
 *
 * Every aggregate here is computed at query time from `medias`. Nothing is
 * stored above media level, so nothing can drift — see docs/DESIGN.md §6.
 */
final class ClientRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * The admin index: every live client with the totals of everything below it.
     *
     * The two LEFT JOINs carry their own deleted_at test rather than putting it
     * in the WHERE clause — moving it would turn them into inner joins and drop
     * clients that have no projects yet.
     *
     * @return list<ClientTotals>
     */
    public function listTotals(): array
    {
        $sql = 'SELECT c.id, c.slug, c.name, c.ctime,
                       COALESCE(SUM(m.size_bytes), 0) AS size_bytes,
                       COUNT(DISTINCT p.id) AS project_count,
                       COUNT(m.id) AS media_count,
                       COALESCE(MAX(m.mtime), 0) AS mtime
                FROM clients c
                LEFT JOIN projects p ON p.client_id = c.id AND p.deleted_at IS NULL
                LEFT JOIN medias m ON m.project_id = p.id AND m.deleted_at IS NULL
                WHERE c.deleted_at IS NULL
                GROUP BY c.id, c.slug, c.name, c.ctime
                ORDER BY c.name';

        $rows = $this->pdo->query($sql)?->fetchAll() ?: [];

        return array_map(
            static fn (array $row): ClientTotals => new ClientTotals(
                id: (int) $row['id'],
                slug: (string) $row['slug'],
                name: (string) $row['name'],
                sizeBytes: (int) $row['size_bytes'],
                projectCount: (int) $row['project_count'],
                mediaCount: (int) $row['media_count'],
                mtime: (int) $row['mtime'],
                ctime: (int) $row['ctime'],
            ),
            $rows,
        );
    }

    /** Total indexed bytes across every live client — the headline figure. */
    public function totalSizeBytes(): int
    {
        $sql = 'SELECT COALESCE(SUM(m.size_bytes), 0)
                FROM medias m
                JOIN projects p ON p.id = m.project_id AND p.deleted_at IS NULL
                JOIN clients c ON c.id = p.client_id AND c.deleted_at IS NULL
                WHERE m.deleted_at IS NULL';

        return (int) $this->pdo->query($sql)?->fetchColumn();
    }

    public function findIdBySlug(string $slug): ?int
    {
        $statement = $this->pdo->prepare('SELECT id FROM clients WHERE slug = ? AND deleted_at IS NULL');
        $statement->execute([$slug]);
        $id = $statement->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    /**
     * Records that the client exists on disk right now, and returns its id.
     *
     * A SELECT followed by an INSERT or UPDATE rather than ON DUPLICATE KEY
     * UPDATE / ON CONFLICT, which are spelled differently on the two engines.
     * At a few hundred rows per scan the extra round-trip is not worth a
     * dialect branch.
     *
     * Re-appearing after a soft delete clears deleted_at: the same folder is
     * the same client, and its history is worth keeping.
     */
    public function upsert(string $slug, string $name, int $ctime, int $scanId, int $seenAt): int
    {
        $statement = $this->pdo->prepare('SELECT id FROM clients WHERE slug = ?');
        $statement->execute([$slug]);
        $id = $statement->fetchColumn();

        if ($id !== false) {
            $update = $this->pdo->prepare(
                'UPDATE clients SET name = ?, ctime = ?, last_seen_at = ?, last_seen_scan = ?,
                        deleted_at = NULL
                 WHERE id = ?',
            );
            $update->execute([$name, $ctime, $seenAt, $scanId, (int) $id]);

            return (int) $id;
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO clients (slug, name, ctime, first_seen_at, last_seen_at, last_seen_scan)
             VALUES (?, ?, ?, ?, ?, ?)',
        );
        $insert->execute([$slug, $name, $ctime, $seenAt, $seenAt, $scanId]);

        // Re-read rather than lastInsertId(): PostgreSQL needs the sequence name
        // for identity columns, and the unique slug makes this unambiguous.
        return (int) $this->findIdBySlug($slug);
    }

    /**
     * Soft-deletes clients the given scan did not see.
     *
     * Keyed on the scan id rather than its start time: two scans can begin
     * within the same second, and second-resolution timestamps cannot tell them
     * apart, which would silently skip the deletion. Scan ids increase
     * monotonically, so "<" also leaves alone anything a newer scan has claimed.
     *
     * Restricted to the scanned slugs when a scan covered only part of the tree,
     * so a partial scan never buries what it did not look at.
     *
     * @param list<string>|null $withinSlugs null sweeps every client
     */
    public function sweep(int $scanId, int $deletedAt, ?array $withinSlugs = null): int
    {
        $sql = 'UPDATE clients SET deleted_at = ? WHERE last_seen_scan < ? AND deleted_at IS NULL';
        $params = [$deletedAt, $scanId];

        if ($withinSlugs !== null) {
            if ($withinSlugs === []) {
                return 0;
            }

            $sql .= ' AND slug IN (' . implode(', ', array_fill(0, count($withinSlugs), '?')) . ')';
            $params = [...$params, ...$withinSlugs];
        }

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement->rowCount();
    }
}
