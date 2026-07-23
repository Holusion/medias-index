<?php

declare(strict_types=1);

namespace MediasIndex\Tests\Http;

use MediasIndex\Auth\AccessDenied;
use MediasIndex\Auth\Guard;
use MediasIndex\Http\Application;
use MediasIndex\Http\Request;
use MediasIndex\Support\Config;
use MediasIndex\Tests\Support\DatabaseTestCase;

/**
 * The whole request path, from URL to rendered HTML, against a real database —
 * which is possible only because the front controller does nothing but delegate.
 */
final class ApplicationTest extends DatabaseTestCase
{
    private function app(?Guard $guard = null): Application
    {
        return Application::create(
            new Config([
                'db' => [
                    'dsn' => (string) getenv('MEDIAS_INDEX_TEST_DSN'),
                    'user' => (string) getenv('MEDIAS_INDEX_TEST_USER'),
                    'password' => (string) getenv('MEDIAS_INDEX_TEST_PASSWORD'),
                ],
                'urls' => [
                    'files' => '/files',
                    'thumbs' => '/thumbs',
                    'origin' => 'https://example.test',
                ],
                'embed' => ['width' => 800, 'height' => 600],
            ]),
            $guard,
        );
    }

    public function testOverviewListsTheClients(): void
    {
        $this->seedMedia('acme', 'expo', 'salle-1', sizeBytes: 2048);

        $response = $this->app()->handle(Request::create('GET', '/'));

        self::assertSame(200, $response->status);
        self::assertStringContainsString('Vue d&#039;ensemble', $response->body);
        self::assertStringContainsString('acme', $response->body);
        self::assertStringContainsString('2,0 Ko', $response->body);
    }

    public function testClientPageWithoutASelectionShowsTheTipAndTheTotals(): void
    {
        $this->seedMedia('acme', 'expo', 'salle-1', sizeBytes: 100);

        $response = $this->app()->handle(Request::create('GET', '/c/acme'));

        self::assertSame(200, $response->status);
        self::assertStringContainsString('Sélectionnez un projet', $response->body);
        self::assertStringContainsString('stat-value', $response->body);
        self::assertStringNotContainsString('is-selected', $response->body);
    }

    public function testTheProjectPageListsItsMedias(): void
    {
        $this->seedMedia('acme', 'expo', 'salle-1', sizeBytes: 100);

        $response = $this->app()->handle(Request::create('GET', '/c/acme/expo'));

        self::assertSame(200, $response->status);
        self::assertStringContainsString('is-selected', $response->body);
        self::assertStringContainsString('salle-1', $response->body);
        self::assertStringContainsString('/files/acme/expo/salle-1/', $response->body);
    }

    /**
     * The main column shows the level it is about and nothing above it: the
     * project page used to open with the client's name as its heading.
     */
    public function testAPageHeadingNamesItsOwnLevelOnly(): void
    {
        $this->seedMedia('acme', 'expo', 'salle-1');

        $body = $this->app()->handle(Request::create('GET', '/c/acme/expo'))->body;

        preg_match_all('/<h1[^>]*>(.*?)<\/h1>/s', $body, $headings);
        self::assertCount(1, $headings[1], 'one heading, for the level the page is about');

        // The heading's visible text, not its markup: the back link inside it
        // legitimately names the parent in its aria-label.
        preg_match('/<h1[^>]*>.*?<span>(.*?)<\/span>/s', $body, $visible);
        self::assertSame('expo', $visible[1], 'the heading names this page, not its parent');

        // The parent is still named — in the navbar breadcrumb, which is where
        // ancestors belong.
        self::assertStringContainsString('<span>acme</span>', $body);

        // ...and the way back up is the glyph on the heading. Asserted as two
        // facts, not one string: the attributes wrap across lines in the source.
        self::assertStringContainsString('class="page-title-back"', $body);
        self::assertStringContainsString('href="/c/acme"', $body);
    }

    /**
     * A stale or mistyped project link must say so, not answer 200 with an empty
     * selection and leave the visitor to work out that it is gone.
     */
    public function testAnUnknownProjectIsA404Page(): void
    {
        $this->seedMedia('acme', 'expo', 'salle-1');

        $response = $this->app()->handle(Request::create('GET', '/c/acme/ghost'));

        self::assertSame(404, $response->status);
        self::assertThemedErrorPage($response->body, 404, 'Introuvable');
    }

    public function testAnUnknownClientIsA404Page(): void
    {
        $response = $this->app()->handle(Request::create('GET', '/c/ghost'));

        self::assertSame(404, $response->status);
        self::assertThemedErrorPage($response->body, 404, 'Introuvable');
    }

    public function testAnUnknownPathIsA404Page(): void
    {
        $response = $this->app()->handle(Request::create('GET', '/nope'));

        self::assertSame(404, $response->status);
        self::assertThemedErrorPage($response->body, 404, 'Introuvable');
    }

    /**
     * Apache serves /files/ itself, so a stale link to a deleted media never
     * reaches routing. Its ErrorDocument sends the request here with the original
     * REQUEST_URI intact and the real status in REDIRECT_STATUS — which is the
     * only thing that says why we were called.
     */
    public function testAnApacheErrorDocumentIsRenderedWithItsOwnStatus(): void
    {
        $response = $this->app()->handle(Request::create(
            'GET',
            '/files/acme/expo/deleted-media/',
            errorStatus: 404,
        ));

        self::assertSame(404, $response->status);
        self::assertThemedErrorPage($response->body, 404, 'Introuvable');
    }

    public function testAnApacheForbiddenIsRenderedAsA403(): void
    {
        $response = $this->app()->handle(Request::create('GET', '/files/', errorStatus: 403));

        self::assertSame(403, $response->status);
        self::assertThemedErrorPage($response->body, 403, 'Accès refusé');
    }

    /** An unrecognised status must still produce a page, never a blank one. */
    public function testAnUnknownStatusFallsBackToTheServerErrorPage(): void
    {
        $response = $this->app()->handle(Request::create('GET', '/error/999'));

        self::assertSame(500, $response->status);
        self::assertThemedErrorPage($response->body, 500, 'Erreur');
    }

    public function testTheErrorRouteIsReachableDirectly(): void
    {
        self::assertSame(404, $this->app()->handle(Request::create('GET', '/error/404'))->status);
        self::assertSame(403, $this->app()->handle(Request::create('GET', '/error/403'))->status);
    }

    /** Errors wear the app's frame — not a bare server default. */
    private static function assertThemedErrorPage(string $body, int $status, string $heading): void
    {
        self::assertStringContainsString('<link rel="stylesheet" href="/assets/css/main.css">', $body);
        self::assertStringContainsString('class="page-header"', $body);
        self::assertStringContainsString('class="error-page"', $body);
        self::assertStringContainsString('>' . $status . '</span>', $body);
        self::assertStringContainsString($heading, $body);
    }

    public function testTheStyleguideRenders(): void
    {
        self::assertSame(200, $this->app()->handle(Request::create('GET', '/styleguide'))->status);
    }

    /**
     * The reason every controller calls the Guard now, while it still allows
     * everything: a refusal has to become a 403 page, not a 500.
     */
    public function testAGuardRefusalBecomesA403(): void
    {
        $denying = new class implements Guard {
            public function requireAdmin(): void
            {
                throw new AccessDenied('nope');
            }

            public function requireClient(string $clientSlug): void
            {
                throw new AccessDenied('nope');
            }
        };

        $this->seedMedia('acme', 'expo', 'salle-1');

        foreach (['/', '/c/acme', '/styleguide'] as $path) {
            $response = $this->app($denying)->handle(Request::create('GET', $path));

            self::assertSame(403, $response->status, $path . ' must be guarded');
            self::assertStringContainsString('Accès refusé', $response->body);
        }
    }

    /** The empty case is the one a count helper gets wrong; check it renders. */
    public function testCountsAgreeWithTheirNouns(): void
    {
        $now = time();
        $this->pdo->exec(
            "INSERT INTO clients (slug, name, first_seen_at, last_seen_at) VALUES ('vide','vide',{$now},{$now})",
        );
        $this->seedMedia('un', 'p', 'a');
        $this->seedMedia('deux', 'p1', 'a');
        $this->seedMedia('deux', 'p2', 'b');

        $body = $this->app()->handle(Request::create('GET', '/'))->body;

        self::assertStringContainsString('pas de projet', $body);
        // \b so "21 projets" cannot satisfy the singular, (?!s) so the plural
        // cannot satisfy it either.
        self::assertMatchesRegularExpression('/\b1 projet(?!s)/', $body);
        self::assertMatchesRegularExpression('/\b2 projets\b/', $body);
        self::assertStringNotContainsString('projet(s)', $body);
    }

    /** The media page is what the embed dialog used to be. */
    public function testTheMediaPageCarriesTheSnippetAndTheLinks(): void
    {
        $this->seedMedia('acme', 'expo', 'salle-1');

        $response = $this->app()->handle(Request::create('GET', '/c/acme/expo/salle-1'));

        self::assertSame(200, $response->status);
        // Absolute: both the copied link and the snippet are used off this site.
        self::assertStringContainsString('data-copy="https://example.test/files/acme/expo/salle-1/"', $response->body);
        self::assertStringContainsString('&lt;iframe src=&quot;https://example.test/files/acme/expo/salle-1/', $response->body);
        self::assertStringContainsString("Code d'intégration", $response->body);
    }

    /**
     * The frame is always on the page but the iframe is not: a virtual tour is
     * tens of megabytes, and nobody should pay for it by scrolling past.
     */
    public function testThePreviewFrameIsPresentButUnloaded(): void
    {
        $this->seedMedia('acme', 'expo', 'salle-1');

        $body = $this->app()->handle(Request::create('GET', '/c/acme/expo/salle-1'))->body;

        self::assertStringContainsString('class="preview-frame"', $body);
        self::assertStringContainsString('data-preview-src="/files/acme/expo/salle-1/"', $body);
        // The frame carries the snippet's ratio, so loading it moves nothing.
        self::assertStringContainsString('aspect-ratio: 800 / 600', $body);
        self::assertStringNotContainsString('<iframe', $body);
    }

    public function testAnUnknownMediaIsA404Page(): void
    {
        $this->seedMedia('acme', 'expo', 'salle-1');

        $response = $this->app()->handle(Request::create('GET', '/c/acme/expo/ghost'));

        self::assertSame(404, $response->status);
        self::assertThemedErrorPage($response->body, 404, 'Introuvable');
    }

    /** Nothing to link to means nothing to open, copy or embed. */
    public function testAnUnusableMediaSaysSoOnItsPage(): void
    {
        $this->seedMedia('acme', 'expo', 'broken', entryPath: null);

        $body = $this->app()->handle(Request::create('GET', '/c/acme/expo/broken'))->body;

        self::assertStringContainsString("n'a pas de point d'entrée", $body);
        self::assertStringNotContainsString('data-copy=', $body);
        self::assertStringNotContainsString('code-block', $body);
    }

    /**
     * The whole card is one link to the media's page, and the copy button sits
     * outside it — a link inside a link is invalid and behaves differently in
     * every browser.
     */
    public function testTheWholeCardIsOneLinkWithTheCopyButtonOutsideIt(): void
    {
        $this->seedMedia('acme', 'expo', 'salle-1');

        $body = $this->app()->handle(Request::create('GET', '/c/acme/expo'))->body;

        self::assertStringContainsString('<a class="media-link" href="/c/acme/expo/salle-1">', $body);
        self::assertStringContainsString('data-copy="https://example.test/files/acme/expo/salle-1/"', $body);

        // Nothing else may be a link inside the card's anchor — its contents,
        // not the match, which necessarily starts with the opening tag.
        preg_match('/<a class="media-link"[^>]*>(.*?)<\/a>/s', $body, $anchor);
        self::assertStringNotContainsString('<a ', $anchor[1], 'no nested anchors');
        self::assertStringNotContainsString('?preview', $body);
    }

    /** Media names come from disk and manifests, so they are never trusted. */
    public function testMediaNamesAreEscaped(): void
    {
        $this->seedMedia('acme', 'expo', 'x');
        $this->pdo->exec('UPDATE medias SET name = \'<script>alert(1)</script>\'');

        $response = $this->app()->handle(Request::create('GET', '/c/acme/expo'));

        self::assertStringNotContainsString('<script>alert(1)</script>', $response->body);
        self::assertStringContainsString('&lt;script&gt;', $response->body);
    }
}
