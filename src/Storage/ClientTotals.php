<?php

declare(strict_types=1);

namespace MediasIndex\Storage;

/**
 * A client as the admin index shows it: identity plus the aggregates of
 * everything below it, so the page needs no follow-up query per row.
 */
final readonly class ClientTotals
{
    public function __construct(
        public int $id,
        public string $slug,
        public string $name,
        public int $sizeBytes,
        public int $projectCount,
        public int $mediaCount,
        /** Newest media mtime anywhere below the client; 0 when it has none. */
        public int $mtime = 0,
        public int $ctime = 0,
    ) {
    }
}
