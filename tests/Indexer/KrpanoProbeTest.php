<?php

declare(strict_types=1);

namespace MediasIndex\Tests\Indexer;

use MediasIndex\Indexer\MediaInspector;
use MediasIndex\Indexer\MediaType;
use MediasIndex\Indexer\Probe\KrpanoProbe;
use MediasIndex\Indexer\Probe\ProbeResult;
use PHPUnit\Framework\TestCase;

final class KrpanoProbeTest extends TestCase
{
    use FixtureTree;

    protected function tearDown(): void
    {
        $this->removeTree();
    }

    /** A minimal tour: what the probe needs, and nothing a real export adds. */
    private function writeTour(string $title = 'Visite', string $thumb = 'panos/s1.tiles/thumb.jpg'): void
    {
        $this->writeFile('tour/tour.xml', <<<XML
            <krpano version="1.24" title="{$title}">
                <scene name="scene_1" title="Entrée" thumburl="{$thumb}">
                    <preview url="panos/s1.tiles/preview.jpg" />
                </scene>
            </krpano>
            XML);
        $this->writeFile('tour/tour.html', '<!doctype html><title>tour</title>');
    }

    private function probe(): ?ProbeResult
    {
        $dir = $this->tree() . '/tour';

        return (new KrpanoProbe())->probe($dir, (new MediaInspector())->inspect($dir));
    }

    public function testRecognisesATourAndReadsItsTitle(): void
    {
        $this->writeTour(title: 'Microscopie');
        $this->writeFile('tour/panos/s1.tiles/thumb.jpg', 'jpeg');

        $result = $this->probe();

        self::assertSame(MediaType::KRPANO, $result?->type);
        self::assertSame('Microscopie', $result->name);
        self::assertSame('1.24', $result->meta['krpano_version']);
        self::assertSame(1, $result->meta['scenes']);
    }

    public function testUsesTheFirstSceneThumbnailAsPreview(): void
    {
        $this->writeTour();
        $this->writeFile('tour/panos/s1.tiles/thumb.jpg', 'jpeg');

        self::assertSame('panos/s1.tiles/thumb.jpg', $this->probe()?->thumbnailSource);
    }

    /**
     * tour.xml is written from the tool's project state, so it happily
     * references files that a partial copy left behind.
     */
    public function testFallsBackToThePreviewWhenTheThumbnailIsMissing(): void
    {
        $this->writeTour();
        $this->writeFile('tour/panos/s1.tiles/preview.jpg', 'jpeg');

        self::assertSame('panos/s1.tiles/preview.jpg', $this->probe()?->thumbnailSource);
    }

    public function testNominatesNoPreviewWhenNothingReferencedExists(): void
    {
        $this->writeTour();

        $result = $this->probe();

        self::assertSame(MediaType::KRPANO, $result?->type);
        self::assertNull($result->thumbnailSource);
    }

    /** The manifest is uploaded content: a path out of the media is refused. */
    public function testRefusesAPreviewPathThatEscapesTheMedia(): void
    {
        $this->writeFile('secret.jpg', 'jpeg');
        $this->writeTour(thumb: '../secret.jpg');

        self::assertNull($this->probe()?->thumbnailSource);
    }

    public function testRefusesAnAbsoluteOrRemotePreviewPath(): void
    {
        $this->writeTour(thumb: 'https://example.com/thumb.jpg');

        self::assertNull($this->probe()?->thumbnailSource);
    }

    /** Any XML can be named tour.xml; only a <krpano> document is one. */
    public function testDeclinesAnXmlFileThatIsNotAKrpanoDocument(): void
    {
        $this->writeFile('tour/tour.xml', '<gallery><image src="a.jpg"/></gallery>');

        self::assertNull($this->probe());
    }

    public function testDeclinesMalformedXml(): void
    {
        $this->writeFile('tour/tour.xml', '<krpano version="1.24"><scene></krpano>');

        self::assertNull($this->probe());
    }

    public function testDeclinesAnEmptyManifest(): void
    {
        $this->writeFile('tour/tour.xml', '');

        self::assertNull($this->probe());
    }

    public function testDeclinesWhenThereIsNoManifestAtAll(): void
    {
        $this->writeFile('tour/index.html', '<!doctype html>');

        self::assertNull($this->probe());
    }

    /** krpano exports tour.html, but a hand-written wrapper should win. */
    public function testPrefersIndexHtmlOverTheExportedTourHtml(): void
    {
        $this->writeTour();
        $this->writeFile('tour/index.html', '<!doctype html><title>wrapper</title>');

        self::assertSame('index.html', $this->probe()?->entryPath);
    }

    public function testFallsBackToTourHtml(): void
    {
        $this->writeTour();

        self::assertSame('tour.html', $this->probe()?->entryPath);
    }

    public function testATourWithoutAnyPageIsStillTypedButUnusable(): void
    {
        $this->writeFile('tour/tour.xml', '<krpano version="1.24"><scene name="s"/></krpano>');

        $result = $this->probe();

        self::assertSame(MediaType::KRPANO, $result?->type);
        self::assertNull($result->entryPath);
        self::assertFalse($result->isUsable());
    }

    public function testTitleIsOptional(): void
    {
        $this->writeFile('tour/tour.xml', '<krpano version="1.24"><scene name="s"/></krpano>');

        self::assertNull($this->probe()?->name);
    }
}
