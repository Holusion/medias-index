<?php

declare(strict_types=1);

namespace MediasIndex\Tests\Indexer;

use MediasIndex\Indexer\MediaInspector;
use MediasIndex\Indexer\Scanner;
use MediasIndex\Indexer\ScanScope;
use MediasIndex\Indexer\Tree;
use MediasIndex\Search\LikeSearch;
use MediasIndex\Storage\ClientRepository;
use MediasIndex\Storage\MediaRepository;
use MediasIndex\Storage\ProjectRepository;
use MediasIndex\Storage\ScanRepository;
use MediasIndex\Tests\Support\DatabaseTestCase;

final class ScannerTest extends DatabaseTestCase
{
    use FixtureTree;

    protected function tearDown(): void
    {
        $this->removeTree();
    }

    private function scanner(): Scanner
    {
        return new Scanner(
            new Tree($this->tree()),
            new MediaInspector(),
            new ClientRepository($this->pdo),
            new ProjectRepository($this->pdo),
            new MediaRepository($this->pdo, new LikeSearch()),
            new ScanRepository($this->pdo),
        );
    }

    private function seedTree(): void
    {
        $this->writeFile('acme/expo/salle-1/index.html', 'hello');
        $this->writeFile('acme/expo/salle-2/viewer.html', 'hi');
        $this->writeFile('acme/expo/notes/readme.txt', 'nothing browsable');
        $this->writeFile('museum/perm/vitrine/index.html', 'x');
    }

    public function testFirstScanIndexesTheWholeTree(): void
    {
        $this->seedTree();

        $result = $this->scanner()->scan(ScanScope::all());

        self::assertSame(2, $result->clients);
        self::assertSame(2, $result->projects);
        self::assertSame(4, $result->medias);
        self::assertSame(1, $result->unusable, 'notes/ has no browsable entry point');

        $entries = $this->pdo->query('SELECT slug, entry_path FROM medias ORDER BY slug')->fetchAll();
        self::assertSame([
            ['slug' => 'notes', 'entry_path' => null],
            ['slug' => 'salle-1', 'entry_path' => 'index.html'],
            ['slug' => 'salle-2', 'entry_path' => 'viewer.html'],
            ['slug' => 'vitrine', 'entry_path' => 'index.html'],
        ], $entries);
    }

    public function testRescanningCreatesNoDuplicates(): void
    {
        $this->seedTree();

        $this->scanner()->scan(ScanScope::all());
        $second = $this->scanner()->scan(ScanScope::all());

        self::assertSame(4, $second->medias);
        self::assertSame(0, $second->deletedMedias);
        self::assertSame(4, (int) $this->pdo->query('SELECT COUNT(*) FROM medias')->fetchColumn());
    }

    public function testMediaRemovedFromDiskIsSoftDeletedNotDropped(): void
    {
        $this->seedTree();
        $this->scanner()->scan(ScanScope::all());

        unlink($this->tree() . '/acme/expo/salle-2/viewer.html');
        rmdir($this->tree() . '/acme/expo/salle-2');

        $result = $this->scanner()->scan(ScanScope::all());

        self::assertSame(1, $result->deletedMedias);
        self::assertSame(4, (int) $this->pdo->query('SELECT COUNT(*) FROM medias')->fetchColumn());
        self::assertSame(
            3,
            (int) $this->pdo->query('SELECT COUNT(*) FROM medias WHERE deleted_at IS NULL')->fetchColumn(),
        );
    }

    /**
     * Regression: the sweep once keyed on the scan's start time, so two scans
     * beginning in the same second were indistinguishable and the deletion was
     * silently skipped. It keys on the monotonic scan id now. A fast scan of a
     * small tree — or the upload hook firing twice — makes this the normal case,
     * not an edge one.
     */
    public function testDeletionIsDetectedEvenWhenTwoScansShareTheSameSecond(): void
    {
        $this->seedTree();
        $first = $this->scanner()->scan(ScanScope::all());

        unlink($this->tree() . '/acme/expo/salle-2/viewer.html');
        rmdir($this->tree() . '/acme/expo/salle-2');

        $second = $this->scanner()->scan(ScanScope::all());

        self::assertNotSame($first->scanId, $second->scanId, 'scan ids are the generation marker');
        self::assertSame(1, $second->deletedMedias);
    }

    /** The same folder coming back is the same media, history included. */
    public function testMediaThatReappearsIsRevivedRatherThanDuplicated(): void
    {
        $this->seedTree();
        $this->scanner()->scan(ScanScope::all());

        unlink($this->tree() . '/acme/expo/salle-2/viewer.html');
        rmdir($this->tree() . '/acme/expo/salle-2');
        $this->scanner()->scan(ScanScope::all());

        $this->writeFile('acme/expo/salle-2/viewer.html', 'back');
        $this->scanner()->scan(ScanScope::all());

        self::assertSame(4, (int) $this->pdo->query('SELECT COUNT(*) FROM medias')->fetchColumn());
        self::assertSame(
            0,
            (int) $this->pdo->query('SELECT COUNT(*) FROM medias WHERE deleted_at IS NOT NULL')->fetchColumn(),
        );
    }

    /**
     * The reason the sweep is scoped. A scan that looked at one client must not
     * bury the clients it never looked at — the bug that makes partial scans
     * dangerous if scoping is added as an afterthought.
     */
    public function testScopedScanDoesNotDeleteAnythingOutsideItsScope(): void
    {
        $this->seedTree();
        $this->scanner()->scan(ScanScope::all());

        $result = $this->scanner()->scan(ScanScope::client('acme'));

        self::assertSame(1, $result->clients);
        self::assertSame(0, $result->deletedMedias);
        self::assertSame(0, $result->deletedClients);
        self::assertSame(
            4,
            (int) $this->pdo->query('SELECT COUNT(*) FROM medias WHERE deleted_at IS NULL')->fetchColumn(),
        );
    }

    public function testScopedScanStillSweepsInsideItsScope(): void
    {
        $this->seedTree();
        $this->scanner()->scan(ScanScope::all());

        unlink($this->tree() . '/acme/expo/notes/readme.txt');
        rmdir($this->tree() . '/acme/expo/notes');

        $result = $this->scanner()->scan(ScanScope::client('acme'));

        self::assertSame(1, $result->deletedMedias);
    }

    public function testScanIsRecordedInTheAuditTrail(): void
    {
        $this->seedTree();
        $result = $this->scanner()->scan(ScanScope::all(), 'hook');

        $scan = (new ScanRepository($this->pdo))->latest();

        self::assertSame($result->scanId, (int) $scan['id']);
        self::assertSame('hook', $scan['triggered_by']);
        self::assertSame('all', $scan['scope']);
        self::assertSame(ScanRepository::OK, $scan['status']);
        self::assertSame(4, json_decode((string) $scan['stats'], true)['medias']);
    }

    /**
     * The probe chain end to end: a more specific probe wins, and what it
     * extracted reaches the database.
     */
    public function testTypesAreAssignedByTheProbeChain(): void
    {
        $this->seedTree();
        $this->writeFile('acme/expo/tour/tour.xml', '<krpano version="1.24" title="Visite"><scene name="s"/></krpano>');
        $this->writeFile('acme/expo/tour/tour.html', '<!doctype html>');

        $this->scanner()->scan(ScanScope::all());

        $types = $this->pdo->query('SELECT slug, type, name FROM medias ORDER BY slug')->fetchAll();

        self::assertSame([
            ['slug' => 'notes', 'type' => 'unknown', 'name' => 'notes'],
            ['slug' => 'salle-1', 'type' => 'html', 'name' => 'salle-1'],
            ['slug' => 'salle-2', 'type' => 'html', 'name' => 'salle-2'],
            ['slug' => 'tour', 'type' => 'krpano', 'name' => 'Visite'],
            ['slug' => 'vitrine', 'type' => 'html', 'name' => 'vitrine'],
        ], $types);
    }

    public function testSizesAndCountsAreStoredPerMedia(): void
    {
        $this->writeFile('acme/expo/salle-1/index.html', str_repeat('a', 120));
        $this->writeFile('acme/expo/salle-1/assets/app.js', str_repeat('b', 80));

        $this->scanner()->scan(ScanScope::all());

        $row = $this->pdo->query("SELECT size_bytes, file_count FROM medias WHERE slug='salle-1'")->fetch();

        self::assertSame(200, (int) $row['size_bytes']);
        self::assertSame(2, (int) $row['file_count']);
    }
}
