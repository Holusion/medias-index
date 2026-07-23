<?php

declare(strict_types=1);

namespace MediasIndex\Auth;

use RuntimeException;

/**
 * Thrown by a Guard. The application turns it into a 403 rather than letting it
 * surface as a 500, so a refusal never looks like a crash.
 */
final class AccessDenied extends RuntimeException
{
}
