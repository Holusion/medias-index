<?php

declare(strict_types=1);

namespace MediasIndex\Http\Controller;

use MediasIndex\Http\ErrorPage;
use MediasIndex\Http\Request;
use MediasIndex\Http\Response;

/**
 * Target of Apache's ErrorDocument directives.
 *
 * Requests that never reach PHP routing — a stale link to a deleted media under
 * /files/, a denied directory listing — are handed here by Apache so they get
 * the same page as an error the application raised itself.
 *
 * Deliberately unguarded: an error page must render even when the visitor is
 * refused, and it says nothing a Guard would protect.
 */
final readonly class ErrorController
{
    public function __construct(private ErrorPage $errorPage)
    {
    }

    /** @param array<string, string> $parameters */
    public function show(Request $request, array $parameters): Response
    {
        // Cast, not trust: the status decides a response code, and it arrives
        // from the URL. ErrorPage falls back to 500 for anything unrecognised.
        return $this->errorPage->render((int) ($parameters['status'] ?? 500));
    }
}
