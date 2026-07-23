<?php

declare(strict_types=1);

namespace MediasIndex\Tests\Http;

use MediasIndex\Auth\AccessDenied;
use MediasIndex\Auth\Guard;
use MediasIndex\Http\Application;
use MediasIndex\Http\Request;
use MediasIndex\Support\Config;
use MediasIndex\Tests\Indexer\FixtureTree;
use MediasIndex\Tests\Support\DatabaseTestCase;

/**
 * The two doors onto the scanner: the token-authenticated hook for machines,
 * and the guarded button for a person.
 */
final class ScanEndpointTest extends DatabaseTestCase
{
    use FixtureTree;

    protected function tearDown(): void
    {
        $this->removeTree();
    }

    private function app(?Guard $guard = null, string $token = 'secret-token'): Application
    {
        $this->writeFile('acme/expo/salle-1/index.html', 'hello');
        $this->makeDirectory('thumbs');

        return Application::create(
            new Config([
                'db' => [
                    'dsn' => (string) getenv('MEDIAS_INDEX_TEST_DSN'),
                    'user' => (string) getenv('MEDIAS_INDEX_TEST_USER'),
                    'password' => (string) getenv('MEDIAS_INDEX_TEST_PASSWORD'),
                ],
                'paths' => ['files' => $this->tree(), 'thumbs' => $this->tree() . '/thumbs'],
                'urls' => [
                    'files' => '/files',
                    'thumbs' => '/thumbs',
                    'origin' => 'https://example.test',
                ],
                'hook' => ['token' => $token],
            ]),
            $guard,
        );
    }

    private function post(string $path, array $body = [], ?string $origin = null, ?Guard $guard = null): array
    {
        $response = $this->app($guard)->handle(Request::create('POST', $path, [], $body, origin: $origin));

        return [$response->status, $response->body, $response->headers];
    }

    // --- the machine door ----------------------------------------------------

    public function testTheHookScansWithAValidToken(): void
    {
        [$status, $body] = $this->post('/hook/scan', ['token' => 'secret-token']);

        self::assertSame(200, $status);
        $payload = json_decode($body, true);
        self::assertSame('ok', $payload['status']);
        self::assertSame(1, $payload['medias']);
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM medias')->fetchColumn());
    }

    public function testTheHookRefusesAWrongToken(): void
    {
        [$status, $body] = $this->post('/hook/scan', ['token' => 'nope']);

        self::assertSame(403, $status);
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM medias')->fetchColumn());
        self::assertStringContainsString('invalid token', $body);
    }

    public function testTheHookRefusesAMissingToken(): void
    {
        self::assertSame(403, $this->post('/hook/scan')[0]);
    }

    /**
     * A blank or placeholder token would otherwise mean an empty submission
     * matches, leaving the endpoint wide open on a half-configured install.
     */
    public function testTheHookIsDisabledUntilATokenIsConfigured(): void
    {
        foreach (['', 'CHANGE_ME'] as $token) {
            $response = $this->app(token: $token)
                ->handle(Request::create('POST', '/hook/scan', [], ['token' => $token]));

            self::assertSame(503, $response->status, 'token: ' . var_export($token, true));
            self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM medias')->fetchColumn());
        }
    }

    /** The hook must not answer a GET: it changes state. */
    public function testTheHookIgnoresGet(): void
    {
        self::assertSame(404, $this->app()->handle(Request::create('GET', '/hook/scan'))->status);
    }

    // --- the operator door ---------------------------------------------------

    public function testTheButtonScansAndRedirects(): void
    {
        [$status, , $headers] = $this->post('/scan', [], 'https://example.test');

        // See other, so refreshing the page it lands on cannot scan again.
        self::assertSame(303, $status);
        self::assertSame('/', $headers['Location']);
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM medias')->fetchColumn());
    }

    /** Stateless CSRF stop-gap while there is no session to keep a token in. */
    public function testAPostFromAnotherSiteIsRefused(): void
    {
        [$status] = $this->post('/scan', [], 'https://evil.test');

        self::assertSame(403, $status);
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM medias')->fetchColumn());
    }

    public function testTheButtonIsGuardedLikeEveryOtherPage(): void
    {
        $denying = new class implements Guard {
            public function requireAdmin(): void
            {
                throw new AccessDenied('nope');
            }

            public function requireClient(string $clientSlug): void
            {
            }
        };

        [$status] = $this->post('/scan', [], 'https://example.test', $denying);

        self::assertSame(403, $status);
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM medias')->fetchColumn());
    }

    /**
     * The operator door must never need the machine's secret: putting it in the
     * page would hand the upload credential to anyone who can view source.
     */
    public function testTheHomePageOffersTheButtonWithoutLeakingTheToken(): void
    {
        $body = $this->app()->handle(Request::create('GET', '/'))->body;

        self::assertStringContainsString('action="/scan"', $body);
        self::assertStringContainsString('method="post"', $body);
        self::assertStringNotContainsString('secret-token', $body);
    }

    public function testTheOverviewReportsTheLastScan(): void
    {
        $this->post('/hook/scan', ['token' => 'secret-token']);

        $body = $this->app()->handle(Request::create('GET', '/'))->body;

        self::assertStringContainsString('Dernier scan', $body);
        self::assertStringContainsString('(hook)', $body);
    }
}
