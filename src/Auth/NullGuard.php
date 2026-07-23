<?php

declare(strict_types=1);

namespace MediasIndex\Auth;

/**
 * Allows everything.
 *
 * The MVP is unauthenticated by design (docs/DESIGN.md §7). This exists so the
 * call sites do, and so that turning authentication on is a one-line change in
 * the wiring rather than a sweep through the controllers.
 */
final class NullGuard implements Guard
{
    public function requireAdmin(): void
    {
    }

    public function requireClient(string $clientSlug): void
    {
    }
}
