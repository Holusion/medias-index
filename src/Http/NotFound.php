<?php

declare(strict_types=1);

namespace MediasIndex\Http;

use RuntimeException;

/**
 * Thrown when a controller is asked for something that does not exist.
 *
 * Lets a controller say "no such client" from wherever it discovers it, without
 * having to build a response there, and guarantees every such case lands on the
 * same page.
 */
final class NotFound extends RuntimeException
{
}
