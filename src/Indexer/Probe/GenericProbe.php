<?php

declare(strict_types=1);

namespace MediasIndex\Indexer\Probe;

use MediasIndex\Indexer\MediaFacts;
use MediasIndex\Indexer\MediaType;

/**
 * Fallback probe: claims every media no other probe recognised, and resolves an
 * entry point by the generic rule from docs/DESIGN.md §2.
 *
 *   1. index.html at the media root
 *   2. otherwise, exactly one *.html / *.htm at the root
 *   3. otherwise none — the media is listed but marked unusable
 *
 * Several html files with no index.html is deliberately case 3 rather than a
 * guess: picking one arbitrarily would produce a link that silently opens the
 * wrong thing.
 *
 * The type follows from that: a browsable page makes it "html", nothing
 * browsable makes it "unknown".
 */
final class GenericProbe implements MediaProbe
{
    public function probe(string $mediaDir, MediaFacts $facts): ?ProbeResult
    {
        $entryPath = self::resolveEntryPath($facts);

        return new ProbeResult(
            type: $entryPath === null ? MediaType::UNKNOWN : MediaType::HTML,
            entryPath: $entryPath,
            thumbnailSource: $facts->images[0] ?? null,
        );
    }

    /** Shared with the more specific probes, which fall back to it. */
    public static function resolveEntryPath(MediaFacts $facts): ?string
    {
        if (in_array('index.html', $facts->rootHtml, true)) {
            return 'index.html';
        }

        return count($facts->rootHtml) === 1 ? $facts->rootHtml[0] : null;
    }
}
