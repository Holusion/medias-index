<?php

declare(strict_types=1);

namespace MediasIndex\Storage;

/**
 * A media as the project index lists it.
 */
final readonly class MediaRow
{
    public function __construct(
        public int $id,
        public string $slug,
        public string $name,
        public string $type,
        /** Null means no browsable entry point: listed, but no link or embed. */
        public ?string $entryPath,
        public ?string $thumbFile,
        public int $sizeBytes,
        public int $fileCount,
        public int $mtime,
        public int $ctime,
    ) {
    }

    public function isUsable(): bool
    {
        return $this->entryPath !== null;
    }
}
