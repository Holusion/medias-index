<?php

declare(strict_types=1);

namespace MediasIndex\Tests\Http;

use MediasIndex\Http\Request;
use PHPUnit\Framework\TestCase;

final class RequestTest extends TestCase
{
    public function testTrailingSlashesAreNormalisedAway(): void
    {
        self::assertSame('/c/acme', Request::create('GET', '/c/acme/')->path);
        self::assertSame('/c/acme', Request::create('GET', '/c/acme')->path);
    }

    public function testRootIsASingleSlash(): void
    {
        self::assertSame('/', Request::create('GET', '/')->path);
        self::assertSame('/', Request::create('GET', '')->path);
    }

    public function testMethodIsUppercased(): void
    {
        self::assertSame('POST', Request::create('post', '/')->method);
    }

    public function testEmptyQueryValuesFallBackToTheDefault(): void
    {
        $request = Request::create('GET', '/', ['p' => '', 'q' => 'salle']);

        self::assertNull($request->query('p'));
        self::assertSame('salle', $request->query('q'));
        self::assertSame('fallback', $request->query('missing', 'fallback'));
    }

    /** A page number from a URL is attacker-controlled; anything odd is ignored. */
    public function testQueryIntRejectsNonNumericValues(): void
    {
        self::assertSame(1, Request::create('GET', '/', ['page' => 'abc'])->queryInt('page', 1));
        self::assertSame(1, Request::create('GET', '/', ['page' => '-3'])->queryInt('page', 1));
        self::assertSame(4, Request::create('GET', '/', ['page' => '4'])->queryInt('page', 1));
    }
}
