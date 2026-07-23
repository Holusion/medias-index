<?php

declare(strict_types=1);

namespace MediasIndex\Http\Controller;

use MediasIndex\Auth\Guard;
use MediasIndex\Http\Request;
use MediasIndex\Http\Response;
use MediasIndex\Support\Config;

/**
 * Exposes bin/doctor.php over HTTP, for the plans where SSH is not available.
 *
 * The script writes its report to output, so it is captured rather than
 * rewritten: one implementation of the checks, reachable both ways.
 */
final readonly class DoctorController
{
    public function __construct(private Guard $guard)
    {
    }

    public function show(Request $request): Response
    {
        $this->guard->requireAdmin();

        ob_start();

        try {
            require Config::rootDir() . '/bin/doctor.php';
        } finally {
            $report = (string) ob_get_clean();
        }

        return Response::text($report);
    }
}
