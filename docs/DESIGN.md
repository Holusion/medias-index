# medias-index — design

Reference description of what this app is and why it is built the way it is.
Written before the MVP so the shape of the code is not re-litigated later.

## 1. Purpose

Index a tree of static content folders and expose it as browsable, searchable
lists, so that a content producer can find a piece of content and copy a link or
an `<iframe>` embed snippet for it.

The app never serves the content itself — Apache does, straight from disk. The
app only stores *metadata about* the content and renders HTML pages.

## 2. Content model

The indexed tree has exactly three fixed levels below the `files/` root:

```
files/
  <client>/            -> client   (1 row)
    <project>/         -> project  (1 row)
      <media>/         -> media    (1 row)
        ...anything...
```

* **client** — a folder directly under `files/`.
* **project** — a folder directly under a client.
* **media** — a folder directly under a project. This is the unit that gets
  linked and embedded. Its contents are opaque to the generic indexer.

Anything that is not a directory at those levels is ignored. Dotfiles and
dot-directories are ignored everywhere. Symlinks are not followed.

The folder name is the identity (`slug`) at each level and is what appears in
URLs. `name` starts out as a copy of the slug so it can later be overridden by a
type-specific scanner (e.g. a title read from a manifest) without a schema
change.

### Media entry point

A media is useful only if something in it can be opened in a browser. The
indexer resolves an **entry point** — a path relative to the media folder — and
stores it in `medias.entry_path`.

Resolution is delegated to a *probe* (see §5). The generic probe:

1. `index.html` at the media root → entry point.
2. otherwise, exactly one `*.html` / `*.htm` file at the media root → entry point.
3. otherwise → `entry_path = NULL`, the media is listed but marked **unusable**
   (no link, no embed snippet).

### Media type

`medias.type` records which probe claimed the media. Probes are tried in order,
most specific first, and the first non-null result wins; `GenericProbe` is always
last and claims whatever is left.

| Type | Recognised by | Extracts |
|---|---|---|
| `krpano` | `tour.xml` at the root **whose root element is `<krpano>`** | title, scene count, version, the first scene's thumbnail |
| `html` | the generic entry-point rule above found a page | — |
| `unknown` | nothing browsable | — |

The type column is a `VARCHAR`, not an enum: adding a type is a new probe and
nothing else — no migration, and no crash when a row written by a newer build is
read by an older one. The UI colour-codes it and falls back to the neutral for
types it does not know.

Two rules learned from the krpano case, worth applying to every future probe:

* **A filename is a hint, not proof.** Any XML can be called `tour.xml`, so it is
  parsed and its root element checked before the type is assigned. A mismatch
  declines, and the generic probe handles the folder as it would any other.
* **A manifest is uploaded content.** Paths read out of it are validated before
  use — absolute paths, `..` and URLs are refused rather than resolved — and
  referenced files are checked to exist, because a manifest routinely outlives
  the files it names. Parsing is capped in size and uses `LIBXML_NONET`, so a
  manifest can never make the indexer fetch a URL.

## 3. Constraints from the target host

Deployment target is **OVH shared web hosting** ("hébergement mutualisé"). The
constraints below shaped most decisions and should be re-checked before adding
anything heavy.

| Constraint | Value | Consequence |
|---|---|---|
| PHP | 7.0 → 8.5 selectable, PHP-FPM by default | target PHP **8.2+**, dev on 8.3 |
| `memory_limit` | 512 MB (128 MB without FPM) | fine; avoid loading whole trees in memory anyway |
| `max_execution_time` | 165 s (120 s without FPM) | a web-triggered full scan must stay well under this |
| `php.ini` | not editable | no runtime tuning, no extension installs |
| Database | MySQL only, **30 concurrent connections**, no external access | one short-lived PDO connection per request |
| PostgreSQL | not on shared hosting (only via a separate Web Cloud Database) | keep SQL portable, do not depend on it |
| Cron | **hourly at best**, minute chosen by OVH | the POST hook is what makes indexing feel immediate |
| SSH | Pro/Performance plans only | deployment must work as a plain file copy |
| Image libs | GD reliable, **Imagick not** | thumbnails use GD only |
| `.htaccess` | Apache, supported | routing and access control live there |

Sources: OVHcloud web hosting technical specifications and language-versions
documentation.

## 4. Deployment layout

GitHub Actions runs the tests, installs runtime dependencies
(`composer install --no-dev`) and uploads the result into `www/app` over SFTP.
Nothing is built on the host.

```
<home>/                      <- account home, parent of the document root
  config/
    medias-index.php         <- credentials; no URL can reach it
  www/                       <- HTTP document root
    .htaccess                <- from deploy/www.htaccess, installed once, NOT part of this repo
    files/                   <- indexed content
    thumbs/                  <- generated thumbnails
    app/                     <- deployed by CI
      .htaccess              <- denies direct access to everything but public/
      public/                <- front controller + static assets
      src/ config/ db/ bin/ vendor/
```

**Everything the deployment must not touch lives outside `app/`.** The upload
mirrors the repository, so anything inside `app/` that is not in git is at risk
of being deleted. That applies to the thumbnail cache and — more importantly —
to the configuration file.

The thumbnail location is configurable, and the cache is disposable: deleting it
costs nothing but a rescan, which regenerates every thumbnail. Losing the
configuration would be fatal, hence the search path below.

### Configuration and secrets

The config file carries the database credentials and the hook secret. It is
never committed and never uploaded. `Config::load()` walks a search path and
takes the first entry that exists, so each environment gets the safest option it
supports without the code having to know which:

1. `MEDIAS_INDEX_CONFIG` — explicit override (docker; also settable through
   `SetEnv` in `.htaccess`).
2. `<home>/config/medias-index.php` — **outside the document root**; no URL can
   reach it and no deploy can delete it. Preferred, but depends on the host
   allowing reads above the document root: `open_basedir` is set by OVH and
   cannot be overridden from a script.
3. `<home>/www/config.php` — inside the document root, denied in `www/.htaccess`,
   but still outside the deployed repository.
4. `<app>/config/config.php` — development only; a mirroring deploy deletes it.

`bin/doctor.php` reports which file was loaded and warns when it sits somewhere a
deploy could delete or a URL could reach.

Two independent mechanisms keep option 3 unreachable, because the obvious one is
not sufficient on its own: a `<Files "config.php">` section is evaluated against
the file a request was *rewritten to*, so the catch-all front-controller rewrite
makes it never match. The effective guard is a `RewriteRule ... [F]` placed
before that catch-all; the `<Files>` section remains as a fallback for when
mod_rewrite is unavailable. Note also that a PHP file returning an array is
executed rather than dumped even when served directly — the deny rules exist for
the case where PHP stops handling `.php` at all.

`www/.htaccess` otherwise rewrites everything except `/files/` and `/thumbs/` to
`app/public/index.php`. Full contents and reasoning are in the README.

## 5. Architecture

Plain PHP 8, no framework. Composer is used for the PSR-4 autoloader and dev
tooling; `vendor/` is installed by CI with `--no-dev`, so PHPUnit and its ~25
transitive packages never reach the hosting space and never enter git. Runtime
dependencies are deliberately kept at zero — router, templating and migrations
are ~100 lines each and cheaper than carrying a framework onto shared hosting.

```
src/
  Support/     Config, Database (PDO factory)
  Storage/     Migrator, dialect layer, repositories, row shapes
  Indexer/     Tree, MediaInspector, probes, Scanner, ThumbnailGenerator
  Search/      SearchStrategy (+ LikeSearch)
  Http/        Application, Router, Request, Response, controllers
  View/        View (templates), Format, Urls
  Auth/        Guard (+ NullGuard)
templates/     plain PHP templates, one per page and per partial
```

`public/index.php` contains three lines: build the `Application`, hand it a
`Request`, send the `Response`. Everything else is in `src/Http/Application.php`,
which hand-wires the object graph — a dozen objects that fit on one screen, and
one less dependency on a host where nothing can be installed. Keeping the front
controller empty is what lets a test drive a real URL through real repositories
and assert on the rendered HTML.

Controllers return a `Response` rather than echoing, templates are files rather
than strings, and `Urls` owns every link the pages emit — including the rule that
an `index.html` entry point links to its directory, which had already been
duplicated twice before it had a home.

### Caching

Content and thumbnails are served by Apache, so their cache headers live in
`www/.htaccess` — **not** in a `.htaccess` inside `files/`. That directory is
written by whatever uploads content, and a mirroring upload would delete a rule
file sitting in it; rules that must not disappear do not belong in the directory
they govern. There is no cost to keeping them at the root either, since Apache
already walks every parent directory looking for `.htaccess` on each request.

| What | Header | Why |
|---|---|---|
| `/thumbs/` | `max-age=31536000, immutable` | the filename carries a digest of the source path, mtime, size and thumbnail settings, so a URL's content never changes |
| `/files/` | `max-age=86400` | not content-addressed — re-uploading replaces files at the same URLs |
| `/files/**.{html,xml,json}` | `max-age=300, must-revalidate` | these *define* a media; a stale one keeps an embed showing the previous version |
| error pages | `no-store` | a 404 stops being true the moment the content is indexed |

The bounded figure for `/files/` is the point: an aggressive cache there would
serve the previous upload for a year. Once the deadline passes, revalidation is
still cheap — Apache's `ETag` and `Last-Modified` turn the follow-up into a 304
rather than a full transfer.

A media's link is its *directory* URL, which does not end in `.html`; it lands in
the short bucket anyway because `DirectoryIndex` issues an internal redirect to
`index.html` and the rules are re-evaluated against that path.

One bound worth knowing on `immutable`: the thumbnail digest covers the source's
mtime and size, not its bytes. A source edited in place to exactly the same size
with its mtime restored would keep its old thumbnail — and with `immutable`,
indefinitely in browsers that already have it. Hashing the contents instead would
mean reading every candidate image on every scan.

### Errors

Every error renders the same themed page, built by `ErrorPage`, whatever raised
it: an unmatched route, a controller throwing `NotFound`, a `Guard` throwing
`AccessDenied`, or an unexpected exception. A 500 says only that something went
wrong — the exception goes to the server log, never to the browser, because the
message can carry a DSN.

Two things are worth knowing about the errors Apache raises *by itself*, which
are the ones a visitor is most likely to meet:

* Content lives under `/files/`, served straight from disk, so a stale link to a
  media that has since been deleted never reaches PHP routing. `ErrorDocument`
  directives in `www/.htaccess` hand those requests to the app so they get the
  same page.
* When Apache does that, it keeps the **original** `REQUEST_URI` — the URL that
  failed, not the `ErrorDocument` path — so there is nothing to route on. The
  real status arrives in `REDIRECT_STATUS`, which the app reads. Note that PHP-FPM
  sets that variable to `200` on ordinary requests, so only values from 400 up
  count as an error.

A project that does not exist is a 404 rather than an empty selection, for the
same reason: answering 200 to a stale link leaves the visitor to work out that
what they asked for is gone.

Four interfaces are the seams the deferred features plug into. Each has exactly
the implementation the MVP needs:

| Seam | Today | Later |
|---|---|---|
| `MediaProbe` | `GenericProbe`, `KrpanoProbe` | one class per recognised content type |
| `SearchStrategy` | `LikeSearch` | `FULLTEXT` / `tsvector` |
| `Guard` | `NullGuard` | admin + client authentication |
| `ScanScope` | `all()` | partial scans from the upload hook |

`Guard` matters most for being called *now*, while it still allows everything:
every controller asks before doing any work, so switching authentication on is a
one-line change in the wiring instead of an audit of every page for the one that
was forgotten. A refusal throws `AccessDenied`, which the application renders as
a 403 rather than letting it surface as a crash.

### Indexation

Entry points: `bin/scan.php` (cron, CLI) and `POST /hook/scan` (upload hook,
shared-secret token). Both call the same `Scanner`.

```
Scanner::scan(ScanScope $scope): ScanResult
```

`ScanScope` describes what to walk — `all()` for the MVP, with `client()`,
`project()` and `media()` reserved for the partial scans the POST hook will
eventually request. Everything downstream is already scope-aware, so adding
partial scans is a matter of parsing POST parameters into a scope.

Per media the scanner walks the subtree once and collects: total size, file
count, newest mtime, candidate entry files, candidate images. Then it probes for
type and entry point, generates a thumbnail if the source changed, and upserts
the row.

**Deletion** is soft. Every scan stamps the rows it touched with its own id in
`last_seen_scan`; rows inside the scanned scope carrying an older id are given a
`deleted_at`. Scoping the sweep is what makes partial scans safe later. Deleted
rows are hidden from the UI but keep their history and thumbnails, and a folder
that reappears revives its original row rather than creating a second one.

The marker is the scan **id**, not the scan's start time. Timestamps have
one-second resolution, so two scans starting in the same second — a fast scan of
a small tree, or the upload hook firing twice — are indistinguishable, and the
sweep silently misses the deletion. Scan ids are monotonic, and comparing with
`<` also leaves alone anything a newer scan has already claimed.

**Aggregates are derived, never stored.** A media's own `size_bytes` and
`file_count` have to be measured by the indexer — files are not rows, so there is
nothing to derive them from. Everything above that level (a project's size, a
client's size, media counts, the newest mtime) is a `SUM`/`COUNT`/`MAX` over
`medias` at query time.

This is a correctness decision, taken knowingly against performance:

* One source of truth. Stored rollups can drift from the rows they summarise;
  derived ones cannot.
* It is what makes **partial scans** safe. Rescanning one project would otherwise
  have to recompute and propagate totals up to its client — the classic way these
  columns go wrong. Derived aggregates simply cannot be stale.
* **Soft deletes** come out right for free: `WHERE deleted_at IS NULL` in the
  aggregate is the whole of it, instead of remembering to adjust every ancestor
  when a media disappears.

At the expected scale — hundreds of medias — a `SUM` over an indexed foreign key
is not worth optimising away. Should that ever change, a materialised rollup is a
cache to add later, not a schema to design around now.

Listing pages must still never compute sizes from **disk** — that was always the
rule, and it is unaffected: they query the database, which sums what the last scan
measured.

Two consequences worth stating:

* A client's size is the sum of its **indexed medias**, not of its directory.
  Loose files outside any media folder are invisible to it. Accepted: these
  figures describe indexed content, and quota enforcement happens elsewhere.
* Aggregating the distinct media *types* of a project is the one aggregate to
  avoid doing in SQL: `GROUP_CONCAT` and `string_agg` are spelled differently on
  the two engines. Select the distinct types and assemble the label in PHP.

Counting bytes and files is squarely the indexer's job: it already opens the
media subtree, and type detection will have it reading file contents there to
extract metadata. Measuring while it walks costs nothing extra.

**Timestamps.** `mtime` is the newest mtime found in the media subtree.
`ctime` is `filectime()` on the media folder, which on Linux is the inode
*change* time, not a creation time — it is stored as-is and labelled honestly in
the UI.

### Thumbnails

Always generated with GD, never referenced in place — Imagick is unreliable on
the target host.

The source is whatever the probe nominated: `GenericProbe` takes the largest
image in the subtree, `KrpanoProbe` the first scene's own thumbnail, which is a
far better picture than the biggest file lying around. "Largest" means largest
**file**, not largest pixel area: deciding on area would mean opening every
candidate.

The image is scaled to *cover* a fixed box and cropped, so every card in a
listing has the same shape; letterboxing would keep more of the picture but make
the list ragged. Output is a JPEG at `thumbs/<media_id>-<digest>.jpg`, the digest
covering the source path, its mtime and size, and the box and quality settings —
so a changed source, or changed settings, produce a new filename and a stale
cache entry can never be served. The superseded file is deleted rather than left
to accumulate, and a media that stops nominating a source loses its thumbnail
instead of keeping the previous one.

Anything that cannot produce an image — a missing file, something that is not an
image despite its extension, a source beyond 40 MP — yields no thumbnail and a
placeholder in the UI. None of it interrupts the scan.

### Presentation

| Route | Page |
|---|---|
| `GET /` | admin index — clients, total size, project names + individual sizes |
| `GET /c/{client}` | client index — projects with last modified, size, media count, media types |
| `GET /c/{client}/{project}` | project index — paginated, searchable media list |
| `GET /thumb/{id}` | thumbnail fallback (regenerates on cache miss) |
| `POST /hook/scan` | trigger a scan, shared-secret token |

The project index shows, per media: thumbnail, folder name, ctime/mtime, size,
type, a link to the content and a ready-to-copy `<iframe>` snippet. Pagination is
`LIMIT`/`OFFSET`.

Every controller calls the `Guard` before rendering (`requireAdmin()` /
`requireClient($slug)`). The MVP ships `NullGuard`, which allows everything.
Adding the two-level auth scheme later means writing a real guard and a login
route, not touching the controllers.

## 6. Database

MySQL 8 today, PostgreSQL kept possible. Rules that keep it portable:

* PDO with prepared statements, no vendor-specific functions in queries.
* Timestamps are **BIGINT unix seconds**, not `DATETIME`/`TIMESTAMP`. This
  sidesteps the biggest portability and timezone trap, and filesystem times are
  unix seconds already.
* No `ENUM`, no `JSON` column type — `VARCHAR` and `TEXT` instead.
* Identity columns are the one unavoidable divergence (`AUTO_INCREMENT` vs
  `GENERATED ALWAYS AS IDENTITY`); a thin dialect layer emits the right DDL.
* Migrations are numbered PHP files in `db/migrations/`, applied by
  `bin/migrate.php`, tracked in `schema_migrations`. No migration framework.

### Tables

Only `medias` carries measurements. `clients` and `projects` hold identity and
scan bookkeeping; their sizes and counts are aggregated from `medias`.

`clients` — `id`, `slug` (unique), `name`, `first_seen_at`, `last_seen_at`,
`deleted_at`.

`projects` — `id`, `client_id`, `slug`, `name`, `ctime`, timestamps as above.
Unique `(client_id, slug)`.

`medias` — `id`, `project_id`, `slug`, `name`, `type`, `entry_path` (nullable),
`thumb_file` (nullable), `size_bytes`, `file_count`, `mtime`, `ctime`,
`meta` (TEXT, JSON-encoded, type-specific), `search_text` (TEXT, nullable),
timestamps as above. Unique `(project_id, slug)`, indexes on `type` and `name`.

`medias.project_id` and `projects.client_id` need indexes regardless of the
foreign keys: every aggregate groups on them.

`scans` — `id`, `trigger`, `scope`, `status`, `started_at`, `finished_at`,
`stats`, `error`. Gives the hook and cron an audit trail and makes concurrent
scans detectable.

`schema_migrations` — `version`, `applied_at`.

### Search

MVP: `LIKE '%term%'` on `medias.name`, behind `SearchStrategy`. At a few hundred
medias per project this is correct and fast enough, and it behaves identically on
both engines.

`search_text` is populated from day one (name plus, later, type-specific
metadata) so the upgrade path is a new `SearchStrategy` implementation over an
already-populated column:

* MySQL — `FULLTEXT` index on `search_text` + `MATCH ... AGAINST`.
* PostgreSQL — `tsvector` + GIN, or `pg_trgm` for fuzzy matching.

## 7. Deferred, by design

Listed so the seams are not mistaken for over-engineering:

1. **Type detection** — extra `MediaProbe` implementations; fills `type`, `meta`,
   a better `name` and a better thumbnail source.
2. **Partial scans** — parse the upload hook's POST parameters into a
   `ScanScope`; the scanner and the delete sweep already honour scopes.
3. **Real search** — swap `SearchStrategy`, add the index.
4. **Authentication** — implement `Guard`, add a login route.

## 8. Local development

`compose.yml` reproduces the production layout: a `php:8.3-apache`
container with `gd` and `pdo_mysql`, OVH-like PHP limits, `www/` as document
root, this repo mounted at `www/app`, and the gitignored `files/` and `thumbs/`
mounted where production has them; plus a MySQL 8 container. Sample content is
generated by `dev/sample-files.php`. See the README for commands.

One rule that is easy to get wrong: `DirectoryIndex` must list `index.html`
before `index.php`. A media's link is its directory URL, so that URL has to
resolve to the media's own entry point; the app is never reached through
`DirectoryIndex`, because the rewrite sends it to `app/public/index.php` first.
