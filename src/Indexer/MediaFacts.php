<?php

declare(strict_types=1);

namespace MediasIndex\Indexer;

/**
 * What one walk of a media directory found. Purely descriptive: it draws no
 * conclusions about entry points, type or thumbnails — probes do that, reading
 * these facts instead of hitting the disk again.
 */
final readonly class MediaFacts
{
    /**
     * @param int          $sizeBytes total size of the media subtree
     * @param int          $fileCount number of regular files in it
     * @param int          $mtime     newest mtime in the subtree, directories
     *                                included, so that a deletion — which only
     *                                bumps its parent — still counts
     * @param int          $ctime     filectime() of the media directory; on Linux
     *                                this is the inode change time, not a creation
     *                                time, and is stored as such
     * @param list<string> $rootHtml  basenames of *.html / *.htm sitting directly
     *                                at the media root, sorted
     * @param list<string> $images    paths of candidate images relative to the
     *                                media root, largest file first
     * @param list<string> $rootFiles every basename sitting directly at the media
     *                                root, sorted — what probes match on to
     *                                recognise a type (tour.xml, scene.json, …)
     *                                without stat-ing the disk again
     */
    public function __construct(
        public int $sizeBytes = 0,
        public int $fileCount = 0,
        public int $mtime = 0,
        public int $ctime = 0,
        public array $rootHtml = [],
        public array $images = [],
        public array $rootFiles = [],
    ) {
    }

    public function hasRootFile(string $name): bool
    {
        return in_array($name, $this->rootFiles, true);
    }
}
