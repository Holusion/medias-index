# medias-index

Indexes a tree of static content folders (`client / project / media`) and lists
them, each with a direct link and a ready-to-copy `<iframe>` snippet.

Apache serves the content itself; this app only stores metadata about it.

Why it is built this way: [`docs/DESIGN.md`](docs/DESIGN.md).

## Requirements

PHP 8.2+ with `pdo_mysql`, `gd`, `json`, `mbstring` · MySQL 8 · Apache with
`mod_rewrite` and `AllowOverride All`. Target host is OVH shared hosting.

Nothing is built on the host — CI installs dependencies, so `vendor/` is not
committed.

## Local development

```bash
docker compose up -d
docker compose exec web composer install
docker compose exec web php bin/migrate.php        # create the schema
docker compose exec web php dev/sample-files.php   # local sample content
docker compose exec web php bin/doctor.php
```

| | |
|---|---|
| App | <http://localhost:8080> |
| Environment check | <http://localhost:8080/doctor> |
| Static content | <http://localhost:8080/files/atelier-nord/expo-lumieres/salle-bleue/> |
| MySQL | `127.0.0.1:13306` — user `medias`, password `medias`, db `medias_index` |

```bash
docker compose exec web ./vendor/bin/phpunit   # tests
docker compose logs -f web                     # apache + php errors
docker compose restart web                     # after editing deploy/www.htaccess
docker compose down -v                         # reset, database included
```

The stack mirrors production: same document-root shape, the same
`deploy/www.htaccess` (copied in when the container starts), and OVH's PHP
limits. Configuration comes from `config/config.dev.php`.

`dev/sample-files.php` writes a sample tree into `files/`, which is gitignored —
it stands in for real client content. Two clients, one to two projects each, one
to two medias per project, between them covering every entry-point case the
indexer has to resolve: an `index.html`, a single non-index `.html`, several
`.html` with no `index.html`, and a folder with no web content at all. Re-running
it is safe; delete `files/` to start over.

## Deployment

Pushing to `main` runs the tests, then uploads to `www/app` over SFTP
(`.github/workflows/deploy.yml`). Repository secrets required: `SFTP_HOST`,
`SFTP_USER`, `SFTP_PASSWORD`.

### One-time setup on the host

**1. Layout.** Anything a deployment must not overwrite lives outside `app/`:

```
<home>/
  config/medias-index.php   <- credentials
  www/
    .htaccess               <- copy of deploy/www.htaccess
    files/                  <- indexed content
    thumbs/                 <- generated thumbnails, writable by PHP
    app/                    <- deployed by CI
```

**2. Apache.** Copy [`deploy/www.htaccess`](deploy/www.htaccess) to
`www/.htaccess`. The two `.htaccess` files inside the repository need nothing.

Besides routing and access rules, that file points Apache's `ErrorDocument` at
the app, so errors Apache answers by itself — most often a stale link to a media
that has since been deleted, served straight from `/files/` — get the
application's error page instead of the bare server default.

**3. Configuration.** Copy `config/config.example.php` to the first of these
locations your host allows, then fill it in:

| | |
|---|---|
| `<home>/config/medias-index.php` | preferred — outside the document root, no URL can reach it |
| `<home>/www/config.php` | inside it, denied in `www/.htaccess` |

Generate the hook token with `php -r 'echo bin2hex(random_bytes(24)), PHP_EOL;'`.

**4. Database.**

```bash
php bin/migrate.php
php bin/doctor.php
```

`bin/doctor.php` checks PHP, extensions, paths and connectivity, reports which
config file was loaded, and warns if it sits somewhere a deploy could delete or a
URL could reach. It also answers at `/doctor` over HTTP.

## Indexing

Two triggers, one scanner:

```bash
php bin/scan.php                                # everything
php bin/scan.php --client=acme                  # one client
php bin/scan.php --client=acme --project=expo   # one project
```

A scan soft-deletes what has vanished from disk, within the scope it looked at —
scanning one client never touches the others. Every run is recorded in the
`scans` table with its counts, duration and any error.

* **Cron** — an OVH scheduled task on `app/bin/scan.php`. OVH runs cron hourly at
  best and picks the minute itself.
* **HTTP hook** — called by whatever uploads the content, which is what makes
  indexing feel immediate:

  ```bash
  curl -X POST -d 'token=YOUR_TOKEN' https://example.com/hook/scan
  ```

## Repository layout

```
bin/        CLI entry points (scan, migrate, doctor)
config/     config.example.php — the real config lives outside the repo
db/         numbered SQL migrations
deploy/     files installed on the host by hand, outside app/
dev/        sample-files.php — generates the local files/ tree
files/      local sample content, gitignored (www/files in production)
docker/     php image for the local stack (compose.yml is at the root)
docs/       DESIGN.md — the reference description of the app
public/     front controller and static assets — the only web-exposed directory
src/        application code (PSR-4, MediasIndex\)
templates/  plain PHP templates
tests/      PHPUnit tests
```
