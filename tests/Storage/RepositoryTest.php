<?php

declare(strict_types=1);

namespace MediasIndex\Tests\Storage;

use MediasIndex\Indexer\MediaType;
use MediasIndex\Search\LikeSearch;
use MediasIndex\Storage\ClientRepository;
use MediasIndex\Storage\MediaRepository;
use MediasIndex\Storage\ProjectRepository;
use MediasIndex\Tests\Support\DatabaseTestCase;

final class RepositoryTest extends DatabaseTestCase
{
    private function clients(): ClientRepository
    {
        return new ClientRepository($this->pdo);
    }

    private function projects(): ProjectRepository
    {
        return new ProjectRepository($this->pdo);
    }

    private function medias(): MediaRepository
    {
        return new MediaRepository($this->pdo, new LikeSearch());
    }

    public function testClientTotalsAggregateEverythingBelow(): void
    {
        $this->seedMedia('acme', 'expo', 'a', sizeBytes: 100);
        $this->seedMedia('acme', 'expo', 'b', sizeBytes: 250);
        $this->seedMedia('acme', 'archive', 'c', sizeBytes: 50);
        $this->seedMedia('museum', 'perm', 'd', sizeBytes: 7);

        $totals = $this->clients()->listTotals();

        self::assertCount(2, $totals);
        self::assertSame('acme', $totals[0]->slug);
        self::assertSame(400, $totals[0]->sizeBytes);
        self::assertSame(2, $totals[0]->projectCount);
        self::assertSame(3, $totals[0]->mediaCount);
        self::assertSame(7, $totals[1]->sizeBytes);
    }

    /**
     * The whole reason aggregates are derived: a soft delete must drop out of
     * every total without anything having to be recomputed.
     */
    public function testSoftDeletedMediasLeaveTheTotalsByThemselves(): void
    {
        $this->seedMedia('acme', 'expo', 'kept', sizeBytes: 100);
        $this->seedMedia('acme', 'expo', 'gone', sizeBytes: 900);

        $this->pdo->exec('UPDATE medias SET deleted_at = ' . time() . " WHERE slug = 'gone'");

        $totals = $this->clients()->listTotals();

        self::assertSame(100, $totals[0]->sizeBytes);
        self::assertSame(1, $totals[0]->mediaCount);
        self::assertSame(100, $this->clients()->totalSizeBytes());
    }

    /**
     * A client with no projects yet must still be listed — the join carries its
     * own deleted_at test precisely so it does not become an inner join.
     */
    public function testClientWithoutProjectsIsStillListed(): void
    {
        $now = time();
        $this->pdo->exec(
            "INSERT INTO clients (slug, name, first_seen_at, last_seen_at) VALUES ('empty','empty',{$now},{$now})",
        );

        $totals = $this->clients()->listTotals();

        self::assertCount(1, $totals);
        self::assertSame(0, $totals[0]->sizeBytes);
        self::assertSame(0, $totals[0]->projectCount);
    }

    public function testProjectTotalsComeBackGroupedByClientInOneQuery(): void
    {
        $this->seedMedia('acme', 'expo', 'a', sizeBytes: 10, mtime: 500);
        $this->seedMedia('acme', 'expo', 'b', sizeBytes: 20, mtime: 900);
        $this->seedMedia('museum', 'perm', 'c', sizeBytes: 5, mtime: 100);

        $byClient = $this->projects()->totalsByClient();

        self::assertCount(2, $byClient);

        $acme = array_values(array_filter(
            array_merge(...array_values($byClient)),
            static fn ($p): bool => $p->slug === 'expo',
        ))[0];

        self::assertSame(30, $acme->sizeBytes);
        self::assertSame(2, $acme->mediaCount);
        self::assertSame(900, $acme->mtime, 'mtime is the newest media in the project');
    }

    public function testDistinctTypesAreAssembledInPhpRatherThanConcatenatedInSql(): void
    {
        $projectId = $this->seedMedia('acme', 'expo', 'a', type: MediaType::HTML);
        $this->seedMedia('acme', 'expo', 'b', type: MediaType::KRPANO);
        $this->seedMedia('acme', 'expo', 'c', type: MediaType::HTML);

        $types = $this->projects()->distinctTypesByProject([$projectId]);

        self::assertSame([MediaType::HTML, MediaType::KRPANO], $types[$projectId]);
    }

    public function testListForClientCarriesTypes(): void
    {
        $this->seedMedia('acme', 'expo', 'a', type: MediaType::KRPANO);
        $clientId = (int) $this->pdo->query("SELECT id FROM clients WHERE slug='acme'")->fetchColumn();

        $projects = $this->projects()->listForClient($clientId);

        self::assertCount(1, $projects);
        self::assertSame([MediaType::KRPANO], $projects[0]->types);
    }

    public function testMediaSearchPaginatesAndReportsTheFullTotal(): void
    {
        $projectId = 0;

        for ($i = 1; $i <= 7; $i++) {
            $projectId = $this->seedMedia('acme', 'expo', sprintf('media-%02d', $i));
        }

        $page = $this->medias()->search($projectId, null, page: 2, perPage: 3);

        self::assertSame(7, $page->total);
        self::assertSame(3, $page->pageCount());
        self::assertCount(3, $page->items);
        self::assertSame('media-04', $page->items[0]->slug);
    }

    public function testMediaSearchFiltersOnTheHaystackAndExcludesDeleted(): void
    {
        $projectId = $this->seedMedia('acme', 'expo', 'salle-bleue');
        $this->seedMedia('acme', 'expo', 'salle-rouge');
        $this->seedMedia('acme', 'expo', 'atelier');
        $this->seedMedia('acme', 'expo', 'salle-verte');
        $this->pdo->exec('UPDATE medias SET deleted_at = ' . time() . " WHERE slug = 'salle-verte'");

        $page = $this->medias()->search($projectId, 'salle');

        self::assertSame(2, $page->total);
        self::assertSame(['salle-bleue', 'salle-rouge'], array_map(static fn ($m) => $m->slug, $page->items));
    }

    public function testUnusableMediasAreListedButFlagged(): void
    {
        $projectId = $this->seedMedia('acme', 'expo', 'broken', entryPath: null);

        $page = $this->medias()->search($projectId, null);

        self::assertCount(1, $page->items);
        self::assertFalse($page->items[0]->isUsable());
    }
}
