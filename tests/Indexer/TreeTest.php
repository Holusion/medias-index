<?php

declare(strict_types=1);

namespace MediasIndex\Tests\Indexer;

use InvalidArgumentException;
use MediasIndex\Indexer\Tree;
use PHPUnit\Framework\TestCase;

final class TreeTest extends TestCase
{
    use FixtureTree;

    protected function tearDown(): void
    {
        $this->removeTree();
    }

    public function testListsTheThreeLevelsSorted(): void
    {
        $this->makeDirectory('zeta/project-b/media-2');
        $this->makeDirectory('alpha/project-a/media-1');

        $tree = new Tree($this->tree());

        self::assertSame(['alpha', 'zeta'], $tree->clients());
        self::assertSame(['project-a'], $tree->projects('alpha'));
        self::assertSame(['media-1'], $tree->medias('alpha', 'project-a'));
    }

    public function testIgnoresFilesDotEntriesAndConfiguredNames(): void
    {
        $this->makeDirectory('client');
        $this->makeDirectory('.hidden');
        $this->makeDirectory('node_modules');
        $this->writeFile('loose.txt');

        $tree = new Tree($this->tree(), ['node_modules']);

        self::assertSame(['client'], $tree->clients());
    }

    public function testDoesNotFollowSymlinkedDirectories(): void
    {
        $this->makeDirectory('real');
        symlink($this->tree() . '/real', $this->tree() . '/linked');

        self::assertSame(['real'], (new Tree($this->tree()))->clients());
    }

    /**
     * Segments arrive from URLs, so a traversal attempt must not resolve to a
     * path outside the content root.
     */
    public function testRejectsSegmentsThatWouldEscapeTheRoot(): void
    {
        $tree = new Tree($this->tree());

        $this->expectException(InvalidArgumentException::class);
        $tree->path('..', 'etc');
    }

    public function testRejectsSegmentsContainingASeparator(): void
    {
        $tree = new Tree($this->tree());

        $this->expectException(InvalidArgumentException::class);
        $tree->path('client/../..');
    }

    public function testBuildsPathsBelowTheRoot(): void
    {
        $tree = new Tree($this->tree());

        self::assertSame($this->tree() . '/a/b/c', $tree->path('a', 'b', 'c'));
        self::assertSame($this->tree(), $tree->path());
    }
}
