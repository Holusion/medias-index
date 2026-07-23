<?php

declare(strict_types=1);

namespace MediasIndex\Indexer\Probe;

use MediasIndex\Indexer\MediaFacts;
use MediasIndex\Indexer\MediaType;
use SimpleXMLElement;

/**
 * Recognises a krpano virtual tour by its tour.xml.
 *
 * The presence of the file is only a hint — any XML could be called tour.xml —
 * so it is parsed and the root element checked. Anything that is not a
 * <krpano> document is declined, and the generic probe handles it as it would
 * any other folder.
 *
 * A tour describes itself well enough to be worth reading: the document title
 * beats the folder name, and each scene names a ready-made thumbnail, which is
 * a far better preview than the largest image lying around in the subtree.
 */
final class KrpanoProbe implements MediaProbe
{
    public const MANIFEST = 'tour.xml';

    /**
     * A tour.xml is a hand-sized document. Anything far larger is not one, and
     * parsing it would cost memory the shared host does not have to spare.
     */
    private const MAX_MANIFEST_BYTES = 4 * 1024 * 1024;

    public function probe(string $mediaDir, MediaFacts $facts): ?ProbeResult
    {
        if (!$facts->hasRootFile(self::MANIFEST)) {
            return null;
        }

        $manifest = $mediaDir . '/' . self::MANIFEST;
        $xml = $this->parse($manifest);

        if ($xml === null || strtolower($xml->getName()) !== 'krpano') {
            return null;
        }

        $scenes = $xml->xpath('//scene') ?: [];

        return new ProbeResult(
            type: MediaType::KRPANO,
            entryPath: $this->resolveEntryPath($facts),
            name: $this->title($xml),
            thumbnailSource: $this->previewImage($mediaDir, $scenes),
            meta: array_filter([
                'krpano_version' => (string) ($xml['version'] ?? ''),
                'scenes' => count($scenes),
            ]),
        );
    }

    /**
     * krpano's own export is tour.html, but a hand-made index.html wrapping the
     * viewer should win — otherwise the link bypasses whatever the author put
     * around it.
     */
    private function resolveEntryPath(MediaFacts $facts): ?string
    {
        foreach (['index.html', 'tour.html'] as $preferred) {
            if (in_array($preferred, $facts->rootHtml, true)) {
                return $preferred;
            }
        }

        return GenericProbe::resolveEntryPath($facts);
    }

    private function title(SimpleXMLElement $xml): ?string
    {
        $title = trim((string) ($xml['title'] ?? ''));

        return $title === '' ? null : $title;
    }

    /**
     * The first scene's thumbnail, falling back to its low-resolution preview.
     *
     * Both are verified to exist before being nominated: tour.xml is written by
     * the krpano tool from the state of the project, and referenced files go
     * missing when a tour is copied around partially.
     *
     * @param list<SimpleXMLElement> $scenes
     */
    private function previewImage(string $mediaDir, array $scenes): ?string
    {
        foreach ($scenes as $scene) {
            $candidates = [
                trim((string) ($scene['thumburl'] ?? '')),
                trim((string) ($scene->preview['url'] ?? '')),
            ];

            foreach ($candidates as $candidate) {
                if ($candidate === '' || !$this->isSafeRelativePath($candidate)) {
                    continue;
                }

                if (is_file($mediaDir . '/' . $candidate)) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    /**
     * tour.xml is uploaded content, so a path out of it is a path out of the
     * media directory. Absolute paths and parent references are refused rather
     * than resolved.
     */
    private function isSafeRelativePath(string $path): bool
    {
        return !str_starts_with($path, '/')
            && !str_contains($path, '..')
            && !str_contains($path, '://')
            && !str_contains($path, "\0");
    }

    private function parse(string $manifest): ?SimpleXMLElement
    {
        $size = @filesize($manifest);

        if ($size === false || $size === 0 || $size > self::MAX_MANIFEST_BYTES) {
            return null;
        }

        $previous = libxml_use_internal_errors(true);

        try {
            // LIBXML_NONET: a manifest must never make the indexer fetch a URL
            // while resolving the document.
            $xml = simplexml_load_file($manifest, SimpleXMLElement::class, LIBXML_NONET);

            return $xml === false ? null : $xml;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }
}
