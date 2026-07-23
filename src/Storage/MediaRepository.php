<?php

declare(strict_types=1);

namespace MediasIndex\Storage;

use MediasIndex\Indexer\MediaFacts;
use MediasIndex\Indexer\Probe\ProbeResult;
use MediasIndex\Search\SearchStrategy;
use PDO;

final class MediaRepository
{
    private const COLUMNS = 'id, slug, name, type, entry_path, thumb_file,
                             size_bytes, file_count, mtime, ctime';

    public function __construct(
        private readonly PDO $pdo,
        private readonly SearchStrategy $search,
    ) {
    }

    /**
     * The project index: one page of medias, optionally filtered.
     *
     * Returns the total alongside the items because the pager needs both, and
     * counting separately from the same conditions is the only way to get it
     * right once LIMIT is involved.
     */
    public function search(int $projectId, ?string $query, int $page = 1, int $perPage = 25): MediaPage
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        $where = 'project_id = ? AND deleted_at IS NULL';
        $params = [$projectId];

        if ($query !== null && trim($query) !== '') {
            [$condition, $searchParams] = $this->search->condition('search_text', $query);

            if ($condition !== '') {
                $where .= ' AND ' . $condition;
                $params = [...$params, ...$searchParams];
            }
        }

        $count = $this->pdo->prepare("SELECT COUNT(*) FROM medias WHERE {$where}");
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $statement = $this->pdo->prepare(
            'SELECT ' . self::COLUMNS . " FROM medias WHERE {$where} ORDER BY name, id LIMIT ? OFFSET ?",
        );

        foreach ($params as $index => $value) {
            $statement->bindValue($index + 1, $value);
        }

        // LIMIT and OFFSET must bind as integers: with emulated prepares off,
        // the driver would otherwise quote them and MySQL rejects the syntax.
        $statement->bindValue(count($params) + 1, $perPage, PDO::PARAM_INT);
        $statement->bindValue(count($params) + 2, ($page - 1) * $perPage, PDO::PARAM_INT);
        $statement->execute();

        return new MediaPage(
            items: array_map(self::hydrate(...), $statement->fetchAll()),
            total: $total,
            page: $page,
            perPage: $perPage,
        );
    }

    public function upsert(
        int $projectId,
        string $slug,
        MediaFacts $facts,
        ProbeResult $probe,
        int $scanId,
        int $seenAt,
    ): int {
        $name = $probe->name ?? $slug;
        $meta = $probe->meta === [] ? null : json_encode($probe->meta, JSON_UNESCAPED_UNICODE);

        // Populated from the first scan so that switching to a real full-text
        // index later needs an index, not a backfill. Probes will add their own
        // extracted metadata here.
        $searchText = trim($name . ' ' . $slug);

        $statement = $this->pdo->prepare('SELECT id FROM medias WHERE project_id = ? AND slug = ?');
        $statement->execute([$projectId, $slug]);
        $id = $statement->fetchColumn();

        $values = [
            $name,
            $probe->type,
            $probe->entryPath,
            $facts->sizeBytes,
            $facts->fileCount,
            $facts->mtime,
            $facts->ctime,
            $meta,
            $searchText,
            $seenAt,
            $scanId,
        ];

        if ($id !== false) {
            $update = $this->pdo->prepare(
                'UPDATE medias SET name = ?, type = ?, entry_path = ?, size_bytes = ?, file_count = ?,
                        mtime = ?, ctime = ?, meta = ?, search_text = ?, last_seen_at = ?,
                        last_seen_scan = ?, deleted_at = NULL
                 WHERE id = ?',
            );
            $update->execute([...$values, (int) $id]);

            return (int) $id;
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO medias (name, type, entry_path, size_bytes, file_count, mtime, ctime, meta,
                                 search_text, last_seen_at, last_seen_scan, project_id, slug, first_seen_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        );
        $insert->execute([...$values, $projectId, $slug, $seenAt]);

        $find = $this->pdo->prepare('SELECT id FROM medias WHERE project_id = ? AND slug = ?');
        $find->execute([$projectId, $slug]);

        return (int) $find->fetchColumn();
    }

    public function setThumbnail(int $mediaId, ?string $thumbFile): void
    {
        $statement = $this->pdo->prepare('UPDATE medias SET thumb_file = ? WHERE id = ?');
        $statement->execute([$thumbFile, $mediaId]);
    }

    /**
     * Keyed on the scan id, not its start time — see ClientRepository::sweep().
     *
     * @param  list<int>|null $withinProjectIds null sweeps every media
     */
    public function sweep(int $scanId, int $deletedAt, ?array $withinProjectIds = null): int
    {
        $sql = 'UPDATE medias SET deleted_at = ? WHERE last_seen_scan < ? AND deleted_at IS NULL';
        $params = [$deletedAt, $scanId];

        if ($withinProjectIds !== null) {
            if ($withinProjectIds === []) {
                return 0;
            }

            $sql .= ' AND project_id IN (' . implode(', ', array_fill(0, count($withinProjectIds), '?')) . ')';
            $params = [...$params, ...$withinProjectIds];
        }

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement->rowCount();
    }

    /** @param array<string, mixed> $row */
    private static function hydrate(array $row): MediaRow
    {
        return new MediaRow(
            id: (int) $row['id'],
            slug: (string) $row['slug'],
            name: (string) $row['name'],
            type: (string) $row['type'],
            entryPath: $row['entry_path'] !== null ? (string) $row['entry_path'] : null,
            thumbFile: $row['thumb_file'] !== null ? (string) $row['thumb_file'] : null,
            sizeBytes: (int) $row['size_bytes'],
            fileCount: (int) $row['file_count'],
            mtime: (int) $row['mtime'],
            ctime: (int) $row['ctime'],
        );
    }
}
