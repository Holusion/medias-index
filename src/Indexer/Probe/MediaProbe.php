<?php

declare(strict_types=1);

namespace MediasIndex\Indexer\Probe;

use MediasIndex\Indexer\MediaFacts;

/**
 * Recognises a kind of media and extracts what it knows about it.
 *
 * This is the seam for the deferred "deep content analysis" work: recognising
 * that a media is structured a particular way, running a specialised scan on it
 * and storing a richer type, name, entry point and metadata. Adding one is
 * additive — a new implementation registered ahead of GenericProbe.
 *
 * Probes are tried in order and the first non-null result wins, so the most
 * specific must come first and GenericProbe last.
 */
interface MediaProbe
{
    /**
     * @param string     $mediaDir absolute path, for probes that need to read files
     * @param MediaFacts $facts    result of the single walk already performed;
     *                             prefer it over touching the disk again
     *
     * @return ProbeResult|null null when this probe does not recognise the media
     */
    public function probe(string $mediaDir, MediaFacts $facts): ?ProbeResult;
}
