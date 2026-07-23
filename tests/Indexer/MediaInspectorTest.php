<?php

declare(strict_types=1);

namespace MediasIndex\Tests\Indexer;

use MediasIndex\Indexer\MediaInspector;
use PHPUnit\Framework\TestCase;

final class MediaInspectorTest extends TestCase
{
    use FixtureTree;

    protected function tearDown(): void
    {
        $this->removeTree();
    }

    public function testMeasuresSizeAndFileCountAcrossTheWholeSubtree(): void
    {
        $this->writeFile('media/index.html', str_repeat('a', 100));
        $this->writeFile('media/assets/style.css', str_repeat('b', 50));
        $this->writeFile('media/assets/deep/data.bin', str_repeat('c', 25));

        $facts = (new MediaInspector())->inspect($this->tree() . '/media');

        self::assertSame(175, $facts->sizeBytes);
        self::assertSame(3, $facts->fileCount);
    }

    public function testMtimeIsTheNewestInTheSubtree(): void
    {
        $this->writeFile('media/old.txt');
        $newest = $this->writeFile('media/nested/new.txt');

        touch($newest, 2_000_000);
        touch($this->tree() . '/media/old.txt', 1_000_000);
        touch($this->tree() . '/media/nested', 1_500_000);
        touch($this->tree() . '/media', 1_200_000);

        $facts = (new MediaInspector())->inspect($this->tree() . '/media');

        self::assertSame(2_000_000, $facts->mtime);
    }

    /**
     * Deleting a file only bumps the mtime of its parent directory, so a media
     * whose files are all old must still look modified when one is removed.
     */
    public function testNestedDirectoryMtimeCountsSoDeletionsAreVisible(): void
    {
        $this->writeFile('media/nested/kept.txt');

        touch($this->tree() . '/media/nested/kept.txt', 1_000_000);
        touch($this->tree() . '/media/nested', 1_900_000);
        touch($this->tree() . '/media', 1_000_000);

        $facts = (new MediaInspector())->inspect($this->tree() . '/media');

        self::assertSame(1_900_000, $facts->mtime);
    }

    public function testOnlyRootLevelHtmlIsReportedAsAnEntryCandidate(): void
    {
        $this->writeFile('media/index.html');
        $this->writeFile('media/about.htm');
        $this->writeFile('media/pages/nested.html');

        $facts = (new MediaInspector())->inspect($this->tree() . '/media');

        self::assertSame(['about.htm', 'index.html'], $facts->rootHtml);
    }

    /** What probes match on to recognise a type without stat-ing the disk. */
    public function testRootFilesListsOnlyTheTopLevelSorted(): void
    {
        $this->writeFile('media/tour.xml');
        $this->writeFile('media/index.html');
        $this->writeFile('media/panos/deep.xml');

        $facts = (new MediaInspector())->inspect($this->tree() . '/media');

        self::assertSame(['index.html', 'tour.xml'], $facts->rootFiles);
        self::assertTrue($facts->hasRootFile('tour.xml'));
        self::assertFalse($facts->hasRootFile('deep.xml'));
    }

    public function testImageCandidatesAreRelativeAndLargestFirst(): void
    {
        $this->writeFile('media/small.png', str_repeat('a', 10));
        $this->writeFile('media/assets/big.jpg', str_repeat('a', 500));
        $this->writeFile('media/notes.txt', str_repeat('a', 999));

        $facts = (new MediaInspector())->inspect($this->tree() . '/media');

        self::assertSame(['assets/big.jpg', 'small.png'], $facts->images);
    }

    public function testImageCandidatesAreCapped(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->writeFile(sprintf('media/img-%02d.png', $i), str_repeat('a', 10 - $i));
        }

        $facts = (new MediaInspector())->inspect($this->tree() . '/media');

        self::assertCount(3, (new MediaInspector(3))->inspect($this->tree() . '/media')->images);
        self::assertCount(10, $facts->images);
    }

    public function testSkipsDotFilesAndSymlinks(): void
    {
        $this->writeFile('media/index.html', str_repeat('a', 100));
        $this->writeFile('media/.hidden', str_repeat('a', 100));
        $this->writeFile('outside/secret.bin', str_repeat('a', 100));
        symlink($this->tree() . '/outside/secret.bin', $this->tree() . '/media/link.bin');

        $facts = (new MediaInspector())->inspect($this->tree() . '/media');

        self::assertSame(1, $facts->fileCount);
        self::assertSame(100, $facts->sizeBytes);
    }

    public function testMissingDirectoryYieldsEmptyFacts(): void
    {
        $facts = (new MediaInspector())->inspect($this->tree() . '/nope');

        self::assertSame(0, $facts->fileCount);
        self::assertSame(0, $facts->sizeBytes);
        self::assertSame([], $facts->rootHtml);
    }
}
