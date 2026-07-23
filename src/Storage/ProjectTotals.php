<?php

declare(strict_types=1);

namespace MediasIndex\Storage;

/**
 * A project with its aggregates, as listed on the admin and client indexes.
 */
final readonly class ProjectTotals
{
    /** @param list<string> $types distinct media types, assembled in PHP */
    public function __construct(
        public int $id,
        public int $clientId,
        public string $slug,
        public string $name,
        public int $sizeBytes,
        public int $mediaCount,
        /** Newest media mtime in the project; 0 when it has no medias. */
        public int $mtime,
        public int $ctime,
        public array $types = [],
    ) {
    }

    /** @param list<string> $types */
    public function withTypes(array $types): self
    {
        return new self(
            $this->id,
            $this->clientId,
            $this->slug,
            $this->name,
            $this->sizeBytes,
            $this->mediaCount,
            $this->mtime,
            $this->ctime,
            $types,
        );
    }
}
