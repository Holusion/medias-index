<?php

declare(strict_types=1);

namespace MediasIndex\Tests\Indexer;

use MediasIndex\Indexer\MediaFacts;
use MediasIndex\Indexer\MediaType;
use MediasIndex\Indexer\Probe\GenericProbe;
use PHPUnit\Framework\TestCase;

final class GenericProbeTest extends TestCase
{
    public function testIndexHtmlWinsOverAnyOtherRootPage(): void
    {
        $result = (new GenericProbe())->probe('/nowhere', new MediaFacts(
            rootHtml: ['credits.html', 'index.html'],
        ));

        self::assertSame('index.html', $result?->entryPath);
        self::assertTrue($result->isUsable());
    }

    public function testASingleRootPageBecomesTheEntryPoint(): void
    {
        $result = (new GenericProbe())->probe('/nowhere', new MediaFacts(
            rootHtml: ['presentation.html'],
        ));

        self::assertSame('presentation.html', $result?->entryPath);
    }

    /**
     * Ambiguity is reported, never guessed: an arbitrary pick would produce a
     * link that silently opens the wrong page.
     */
    public function testSeveralRootPagesWithoutAnIndexAreUnusable(): void
    {
        $result = (new GenericProbe())->probe('/nowhere', new MediaFacts(
            rootHtml: ['apres.html', 'avant.html'],
        ));

        self::assertNull($result?->entryPath);
        self::assertFalse($result->isUsable());
    }

    public function testNoHtmlAtAllIsUnusable(): void
    {
        $result = (new GenericProbe())->probe('/nowhere', new MediaFacts(fileCount: 3));

        self::assertNull($result?->entryPath);
    }

    public function testAlwaysClaimsTheMediaSoItIsStillIndexed(): void
    {
        $result = (new GenericProbe())->probe('/nowhere', new MediaFacts());

        self::assertSame(MediaType::UNKNOWN, $result?->type);
    }

    public function testABrowsablePageMakesItHtml(): void
    {
        $result = (new GenericProbe())->probe('/nowhere', new MediaFacts(rootHtml: ['index.html']));

        self::assertSame(MediaType::HTML, $result?->type);
    }

    public function testNothingBrowsableMakesItUnknown(): void
    {
        $result = (new GenericProbe())->probe('/nowhere', new MediaFacts(
            rootHtml: ['a.html', 'b.html'],
        ));

        self::assertSame(MediaType::UNKNOWN, $result?->type);
    }

    public function testNominatesTheLargestImageAsThumbnailSource(): void
    {
        $result = (new GenericProbe())->probe('/nowhere', new MediaFacts(
            images: ['assets/cover.jpg', 'small.png'],
        ));

        self::assertSame('assets/cover.jpg', $result?->thumbnailSource);
    }
}
