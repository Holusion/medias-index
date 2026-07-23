<?php

/**
 * Initial schema — see docs/DESIGN.md §6.
 *
 * Two things shape it:
 *
 * 1. Only `medias` carries measurements. A project's or client's size, media
 *    count and last-modified are SUM/COUNT/MAX over `medias` at query time, so
 *    they cannot drift, partial rescans cannot leave them stale, and soft
 *    deletes are handled by a WHERE clause instead of by remembering to adjust
 *    every ancestor.
 * 2. It has to run unchanged on PostgreSQL later, so: BIGINT unix seconds
 *    rather than DATETIME, VARCHAR/TEXT rather than ENUM or JSON, separate
 *    CREATE INDEX statements, explicitly named indexes.
 *
 * Every table a scan touches carries last_seen_scan as well as last_seen_at.
 * The sweep that soft-deletes vanished rows keys on the scan id, not the
 * timestamp: two scans can start within the same second — a fast scan of a
 * small tree, or the upload hook firing twice — and second-resolution
 * timestamps cannot tell them apart, so deletions would be silently missed.
 * last_seen_at stays because "when was this last seen" is worth showing.
 */

declare(strict_types=1);

use MediasIndex\Storage\Dialect;
use MediasIndex\Storage\Migration;

return new class implements Migration {
    public function statements(Dialect $dialect): array
    {
        $id = $dialect->identityPrimaryKey();
        $suffix = $dialect->tableSuffix();

        return [
            // --- clients -----------------------------------------------------
            // Identity and scan bookkeeping only.
            "CREATE TABLE clients (
                id {$id},
                slug VARCHAR(190) NOT NULL,
                name VARCHAR(255) NOT NULL,
                ctime BIGINT NOT NULL DEFAULT 0,
                first_seen_at BIGINT NOT NULL,
                last_seen_at BIGINT NOT NULL,
                last_seen_scan BIGINT NOT NULL DEFAULT 0,
                deleted_at BIGINT NULL,
                CONSTRAINT uq_clients_slug UNIQUE (slug)
            ){$suffix}",

            // --- projects ----------------------------------------------------
            // ctime is filectime() on the directory: on Linux the inode change
            // time, not a creation time. Stored as reported and labelled
            // honestly in the UI. mtime is derived from the medias below.
            "CREATE TABLE projects (
                id {$id},
                client_id BIGINT NOT NULL,
                slug VARCHAR(190) NOT NULL,
                name VARCHAR(255) NOT NULL,
                ctime BIGINT NOT NULL DEFAULT 0,
                first_seen_at BIGINT NOT NULL,
                last_seen_at BIGINT NOT NULL,
                last_seen_scan BIGINT NOT NULL DEFAULT 0,
                deleted_at BIGINT NULL,
                CONSTRAINT uq_projects_client_slug UNIQUE (client_id, slug),
                CONSTRAINT fk_projects_client FOREIGN KEY (client_id)
                    REFERENCES clients (id) ON DELETE CASCADE
            ){$suffix}",

            // --- medias ------------------------------------------------------
            // entry_path NULL means the media was indexed but has no browsable
            // entry point: it is listed, without a link or an embed snippet.
            // meta and search_text are the extension points for type detection
            // and full-text search; search_text is populated from the start so
            // that adding an index later needs no backfill.
            "CREATE TABLE medias (
                id {$id},
                project_id BIGINT NOT NULL,
                slug VARCHAR(190) NOT NULL,
                name VARCHAR(255) NOT NULL,
                -- Literal rather than MediaType::UNKNOWN: a migration must keep
                -- producing the same schema even if the constant is renamed.
                type VARCHAR(64) NOT NULL DEFAULT 'unknown',
                entry_path VARCHAR(1024) NULL,
                thumb_file VARCHAR(255) NULL,
                size_bytes BIGINT NOT NULL DEFAULT 0,
                file_count INT NOT NULL DEFAULT 0,
                mtime BIGINT NOT NULL DEFAULT 0,
                ctime BIGINT NOT NULL DEFAULT 0,
                meta TEXT NULL,
                search_text TEXT NULL,
                first_seen_at BIGINT NOT NULL,
                last_seen_at BIGINT NOT NULL,
                last_seen_scan BIGINT NOT NULL DEFAULT 0,
                deleted_at BIGINT NULL,
                CONSTRAINT uq_medias_project_slug UNIQUE (project_id, slug),
                CONSTRAINT fk_medias_project FOREIGN KEY (project_id)
                    REFERENCES projects (id) ON DELETE CASCADE
            ){$suffix}",

            // Ordering key for the paginated project index. The grouping key for
            // every aggregate — project_id, and client_id on projects — is
            // already the leading column of the UNIQUE constraints above, which
            // both engines can use, so no separate index is needed for it.
            'CREATE INDEX idx_medias_project_name ON medias (project_id, name)',
            'CREATE INDEX idx_medias_type ON medias (type)',

            // Sweeping medias not seen by a scan, and hiding deleted ones.
            'CREATE INDEX idx_medias_last_seen_scan ON medias (last_seen_scan)',
            'CREATE INDEX idx_medias_deleted_at ON medias (deleted_at)',

            // --- scans -------------------------------------------------------
            // Audit trail for cron and hook runs, and what makes a concurrent
            // scan detectable. "trigger" is a reserved word in both engines,
            // hence triggered_by.
            "CREATE TABLE scans (
                id {$id},
                triggered_by VARCHAR(32) NOT NULL,
                scope VARCHAR(1024) NULL,
                status VARCHAR(16) NOT NULL,
                started_at BIGINT NOT NULL,
                finished_at BIGINT NULL,
                stats TEXT NULL,
                error TEXT NULL
            ){$suffix}",

            'CREATE INDEX idx_scans_started_at ON scans (started_at)',
        ];
    }
};
