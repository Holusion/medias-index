<?php

declare(strict_types=1);

namespace MediasIndex\Storage;

/**
 * One page of a paginated, possibly filtered media listing.
 *
 * Carries the total alongside the items because the pager needs both and they
 * come from the same call.
 */
final readonly class MediaPage
{
    /** @param list<MediaRow> $items */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $perPage,
    ) {
    }

    public function pageCount(): int
    {
        return max(1, (int) ceil($this->total / max(1, $this->perPage)));
    }
}
