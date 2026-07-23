<?php

declare(strict_types=1);

namespace MediasIndex\Http\Controller;

use Closure;
use MediasIndex\Auth\Guard;
use MediasIndex\Http\Request;
use MediasIndex\Http\Response;
use MediasIndex\Indexer\ScanScope;
use MediasIndex\Indexer\Scanner;
use Throwable;

/**
 * Triggers an index scan.
 *
 * Two doors onto the same scanner, deliberately separate:
 *
 *   POST /hook/scan   for machines — whatever uploads content — authenticated
 *                     by the shared token from the configuration.
 *   POST /scan        for a person clicking the button on the overview page,
 *                     authenticated by the Guard like every other page.
 *
 * They are not merged because the button would then have to carry the token in
 * the HTML, handing the upload credential to anyone who can view source. An
 * operator is already identified by whatever guards the pages; making them
 * prove it a second time with a machine's secret is both redundant and a leak.
 */
final readonly class ScanController
{
    /**
     * @param Closure(): Scanner $scanner built on demand, not up front: a scan
     *                                    needs the content and thumbnail paths,
     *                                    and a missing one must not stop the
     *                                    browsing pages from rendering
     */
    public function __construct(
        private Closure $scanner,
        private Guard $guard,
        private string $token,
        private string $expectedOrigin,
    ) {
    }

    /** The machine door: token in, JSON out. */
    public function hook(Request $request): Response
    {
        if ($this->token === '' || $this->token === 'CHANGE_ME') {
            return Response::json(['error' => 'hook disabled: no token configured'], 503);
        }

        $given = $request->post('token') ?? '';

        // hash_equals, not ===: a plain comparison returns as soon as two bytes
        // differ, which leaks the token a character at a time.
        if (!hash_equals($this->token, $given)) {
            return Response::json(['error' => 'invalid token'], 403);
        }

        try {
            $result = ($this->scanner)()->scan(ScanScope::all(), 'hook');
        } catch (Throwable $e) {
            error_log('[medias-index] hook scan failed: ' . $e->getMessage());

            return Response::json(['error' => 'scan failed'], 500);
        }

        return Response::json(['status' => 'ok', 'scan' => $result->scanId, ...$result->toArray()]);
    }

    /** The operator door: the button on the overview page. */
    public function trigger(Request $request): Response
    {
        $this->guard->requireAdmin();

        if (!$this->isSameOrigin($request)) {
            return Response::text('cross-origin request refused', 403);
        }

        try {
            ($this->scanner)()->scan(ScanScope::all(), 'web');
        } catch (Throwable $e) {
            error_log('[medias-index] web scan failed: ' . $e->getMessage());
        }

        // See other, so refreshing the page it lands on cannot scan again.
        return Response::redirect('/');
    }

    /**
     * Rejects a POST issued by another site.
     *
     * Stop-gap for CSRF while the app is unauthenticated: a form token needs
     * somewhere to keep state, and there is no session yet. Browsers always send
     * Origin on a cross-origin POST, so comparing it is cheap and stateless. An
     * absent header means the request came from something that is not a browser
     * — curl, a test — which this check is not the right defence against.
     */
    private function isSameOrigin(Request $request): bool
    {
        $origin = $request->origin();

        return $origin === null
            || $this->expectedOrigin === ''
            || $origin === $this->expectedOrigin;
    }
}
