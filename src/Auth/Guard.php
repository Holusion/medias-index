<?php

declare(strict_types=1);

namespace MediasIndex\Auth;

/**
 * Decides whether the current visitor may see a page.
 *
 * Every controller calls this before doing any work, even now that NullGuard
 * lets everything through. That is the whole point: adding the two-level scheme
 * later means writing one implementation and a login route, instead of auditing
 * every page for the one that was forgotten.
 */
interface Guard
{
    /** The overview and anything spanning all clients. */
    public function requireAdmin(): void;

    /** A single client's own pages. Admins pass this too. */
    public function requireClient(string $clientSlug): void;
}
