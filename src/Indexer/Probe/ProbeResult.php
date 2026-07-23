<?php

declare(strict_types=1);

namespace MediasIndex\Indexer\Probe;

/**
 * What a probe concluded about a media.
 */
final readonly class ProbeResult
{
    /**
     * @param string                $type            probe that claimed the media,
     *                                               stored in medias.type
     * @param string|null           $entryPath       browsable entry point, relative
     *                                               to the media root; null means the
     *                                               media is listed but gets no link
     *                                               and no embed snippet
     * @param string|null           $name            display name if the probe found a
     *                                               better one than the folder name
     * @param string|null           $thumbnailSource image to derive the thumbnail
     *                                               from, relative to the media root
     * @param array<string, mixed>  $meta            type-specific extras, stored as
     *                                               JSON in medias.meta
     */
    public function __construct(
        public string $type,
        public ?string $entryPath = null,
        public ?string $name = null,
        public ?string $thumbnailSource = null,
        public array $meta = [],
    ) {
    }

    public function isUsable(): bool
    {
        return $this->entryPath !== null;
    }
}
