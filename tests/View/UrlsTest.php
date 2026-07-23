<?php

declare(strict_types=1);

namespace MediasIndex\Tests\View;

use MediasIndex\View\Urls;
use PHPUnit\Framework\TestCase;

final class UrlsTest extends TestCase
{
    private function urls(string $origin = ''): Urls
    {
        return new Urls('/files', '/thumbs', $origin);
    }

    /**
     * The rule that was duplicated before this class existed: an index.html
     * entry links to the directory, so Apache's DirectoryIndex serves it and the
     * URL stays clean.
     */
    public function testIndexHtmlLinksToTheDirectory(): void
    {
        self::assertSame(
            '/files/acme/expo/salle/',
            $this->urls()->content('acme', 'expo', 'salle', 'index.html'),
        );
    }

    public function testAnyOtherEntryPointIsNamedExplicitly(): void
    {
        self::assertSame(
            '/files/acme/expo/salle/tour.html',
            $this->urls()->content('acme', 'expo', 'salle', 'tour.html'),
        );
    }

    public function testAnEntryPointInASubdirectoryKeepsItsSeparators(): void
    {
        self::assertSame(
            '/files/acme/expo/salle/web/index.html',
            $this->urls()->content('acme', 'expo', 'salle', 'web/index.html'),
        );
    }

    public function testAMediaWithoutAnEntryPointHasNoLink(): void
    {
        self::assertNull($this->urls()->content('acme', 'expo', 'salle', null));
    }

    public function testSlugsAreUrlEncoded(): void
    {
        self::assertSame(
            '/files/mus%C3%A9e/expo%20d%27art/salle%201/',
            $this->urls()->content('musée', "expo d'art", 'salle 1', 'index.html'),
        );
    }

    public function testClientAndProjectLinks(): void
    {
        self::assertSame('/c/acme', $this->urls()->client('acme'));
        self::assertSame('/c/acme?p=expo%20d%27art', $this->urls()->project('acme', "expo d'art"));
        self::assertSame('/c/acme?p=expo&page=3', $this->urls()->projectPage('acme', 'expo', 3));
    }

    public function testThumbnailsAreOptional(): void
    {
        self::assertNull($this->urls()->thumbnail(null));
        self::assertSame('/thumbs/7-abc.jpg', $this->urls()->thumbnail('7-abc.jpg'));
    }

    /** Absolute form is what an embed snippet has to carry. */
    public function testAbsolutePrefixesTheConfiguredOrigin(): void
    {
        $urls = $this->urls('https://example.com');

        self::assertSame('https://example.com/files/a/b/c/', $urls->absolute('/files/a/b/c/'));
        self::assertSame('/files/a/b/c/', $this->urls()->absolute('/files/a/b/c/'));
    }

    public function testAbsoluteLeavesAnAlreadyAbsoluteUrlAlone(): void
    {
        self::assertSame(
            'https://cdn.example.org/x',
            $this->urls('https://example.com')->absolute('https://cdn.example.org/x'),
        );
    }
}
