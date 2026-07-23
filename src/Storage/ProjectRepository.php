<?php

declare(strict_types=1);

namespace MediasIndex\Storage;

use PDO;

/**
 * Projects, with their aggregates derived from `medias`.
 */
final class ProjectRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Project totals grouped by client id.
     *
     * One query whether it feeds the admin index (every client) or a client page
     * (one client): passing a client id filters, it does not change the shape.
     * That is what keeps the admin index from running a query per client.
     *
     * @return array<int, list<ProjectTotals>>
     */
    public function totalsByClient(?int $clientId = null): array
    {
        $sql = 'SELECT p.id, p.client_id, p.slug, p.name, p.ctime,
                       COALESCE(SUM(m.size_bytes), 0) AS size_bytes,
                       COUNT(m.id) AS media_count,
                       COALESCE(MAX(m.mtime), 0) AS mtime
                FROM projects p
                LEFT JOIN medias m ON m.project_id = p.id AND m.deleted_at IS NULL
                WHERE p.deleted_at IS NULL';
        $params = [];

        if ($clientId !== null) {
            $sql .= ' AND p.client_id = ?';
            $params[] = $clientId;
        }

        $sql .= ' GROUP BY p.id, p.client_id, p.slug, p.name, p.ctime ORDER BY p.name';

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        $byClient = [];

        foreach ($statement->fetchAll() as $row) {
            $byClient[(int) $row['client_id']][] = new ProjectTotals(
                id: (int) $row['id'],
                clientId: (int) $row['client_id'],
                slug: (string) $row['slug'],
                name: (string) $row['name'],
                sizeBytes: (int) $row['size_bytes'],
                mediaCount: (int) $row['media_count'],
                mtime: (int) $row['mtime'],
                ctime: (int) $row['ctime'],
            );
        }

        return $byClient;
    }

    /**
     * Distinct media types per project.
     *
     * Deliberately not a GROUP_CONCAT: that is spelled string_agg on
     * PostgreSQL, and this would put the difference in the middle of the most
     * used query. Distinct pairs come back and PHP assembles them.
     *
     * @param  list<int> $projectIds
     * @return array<int, list<string>>
     */
    public function distinctTypesByProject(array $projectIds): array
    {
        if ($projectIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($projectIds), '?'));
        $statement = $this->pdo->prepare(
            "SELECT DISTINCT project_id, type FROM medias
             WHERE deleted_at IS NULL AND project_id IN ({$placeholders})
             ORDER BY project_id, type",
        );
        $statement->execute($projectIds);

        $types = [];

        foreach ($statement->fetchAll() as $row) {
            $types[(int) $row['project_id']][] = (string) $row['type'];
        }

        return $types;
    }

    /** @return list<ProjectTotals> totals for one client, types included */
    public function listForClient(int $clientId): array
    {
        $projects = $this->totalsByClient($clientId)[$clientId] ?? [];
        $types = $this->distinctTypesByProject(array_map(static fn (ProjectTotals $p): int => $p->id, $projects));

        return array_map(
            static fn (ProjectTotals $p): ProjectTotals => $p->withTypes($types[$p->id] ?? []),
            $projects,
        );
    }

    public function findIdBySlug(int $clientId, string $slug): ?int
    {
        $statement = $this->pdo->prepare(
            'SELECT id FROM projects WHERE client_id = ? AND slug = ? AND deleted_at IS NULL',
        );
        $statement->execute([$clientId, $slug]);
        $id = $statement->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    public function upsert(int $clientId, string $slug, string $name, int $ctime, int $scanId, int $seenAt): int
    {
        $statement = $this->pdo->prepare('SELECT id FROM projects WHERE client_id = ? AND slug = ?');
        $statement->execute([$clientId, $slug]);
        $id = $statement->fetchColumn();

        if ($id !== false) {
            $update = $this->pdo->prepare(
                'UPDATE projects SET name = ?, ctime = ?, last_seen_at = ?, last_seen_scan = ?,
                        deleted_at = NULL
                 WHERE id = ?',
            );
            $update->execute([$name, $ctime, $seenAt, $scanId, (int) $id]);

            return (int) $id;
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO projects (client_id, slug, name, ctime, first_seen_at, last_seen_at, last_seen_scan)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
        );
        $insert->execute([$clientId, $slug, $name, $ctime, $seenAt, $seenAt, $scanId]);

        return (int) $this->findIdBySlug($clientId, $slug);
    }

    /**
     * Keyed on the scan id, not its start time — see ClientRepository::sweep().
     *
     * @param  list<int>|null $withinClientIds null sweeps every project
     */
    public function sweep(int $scanId, int $deletedAt, ?array $withinClientIds = null): int
    {
        $sql = 'UPDATE projects SET deleted_at = ? WHERE last_seen_scan < ? AND deleted_at IS NULL';
        $params = [$deletedAt, $scanId];

        if ($withinClientIds !== null) {
            if ($withinClientIds === []) {
                return 0;
            }

            $sql .= ' AND client_id IN (' . implode(', ', array_fill(0, count($withinClientIds), '?')) . ')';
            $params = [...$params, ...$withinClientIds];
        }

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement->rowCount();
    }
}
