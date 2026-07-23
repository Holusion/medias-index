<?php

declare(strict_types=1);

namespace MediasIndex\Tests\Http;

use MediasIndex\Http\Request;
use MediasIndex\Http\Response;
use MediasIndex\Http\Router;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    private function router(): Router
    {
        $router = new Router();
        $router->get('/', static fn (): Response => Response::text('home'));
        $router->get('/c/{client}', static fn (Request $r, array $p): Response => Response::text($p['client']));
        $router->post('/hook/scan', static fn (): Response => Response::text('scanned'));

        return $router;
    }

    public function testMatchesAStaticRoute(): void
    {
        self::assertSame('home', $this->router()->dispatch(Request::create('GET', '/'))?->body);
    }

    public function testPassesPlaceholdersToTheHandler(): void
    {
        self::assertSame('acme', $this->router()->dispatch(Request::create('GET', '/c/acme'))?->body);
    }

    public function testPlaceholderValuesAreDecoded(): void
    {
        self::assertSame('mus e', $this->router()->dispatch(Request::create('GET', '/c/mus%20e'))?->body);
    }

    /**
     * A placeholder must never swallow a slash: otherwise "/c/a/b" would match
     * and a slug could carry a whole path — including a traversal — into a
     * handler that expects one segment.
     */
    public function testAPlaceholderNeverSpansSeveralSegments(): void
    {
        self::assertNull($this->router()->dispatch(Request::create('GET', '/c/acme/expo')));
    }

    public function testMethodMustMatch(): void
    {
        self::assertNull($this->router()->dispatch(Request::create('POST', '/c/acme')));
        self::assertSame('scanned', $this->router()->dispatch(Request::create('POST', '/hook/scan'))?->body);
    }

    /**
     * HEAD is GET without the body. Answering 404 to it while GET returns 200
     * misleads every link checker, proxy and uptime monitor that uses it.
     */
    public function testHeadIsServedByTheMatchingGetRoute(): void
    {
        self::assertSame('home', $this->router()->dispatch(Request::create('HEAD', '/'))?->body);
        self::assertSame('acme', $this->router()->dispatch(Request::create('HEAD', '/c/acme'))?->body);
    }

    /** A HEAD must not reach a route that only accepts POST. */
    public function testHeadDoesNotReachAPostOnlyRoute(): void
    {
        self::assertNull($this->router()->dispatch(Request::create('HEAD', '/hook/scan')));
    }

    public function testUnknownPathReturnsNullSoTheCallerDecides(): void
    {
        self::assertNull($this->router()->dispatch(Request::create('GET', '/nope')));
    }

    /** Literal parts are quoted, so a dot is a dot and not "any character". */
    public function testLiteralsAreNotTreatedAsRegex(): void
    {
        $router = new Router();
        $router->get('/doctor.php', static fn (): Response => Response::text('doctor'));

        self::assertNull($router->dispatch(Request::create('GET', '/doctorxphp')));
        self::assertSame('doctor', $router->dispatch(Request::create('GET', '/doctor.php'))?->body);
    }

    public function testFirstMatchingRouteWins(): void
    {
        $router = new Router();
        $router->get('/c/{client}', static fn (): Response => Response::text('first'));
        $router->get('/c/{other}', static fn (): Response => Response::text('second'));

        self::assertSame('first', $router->dispatch(Request::create('GET', '/c/x'))?->body);
    }
}
