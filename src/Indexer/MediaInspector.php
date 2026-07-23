<?php

declare(strict_types=1);

namespace MediasIndex\Indexer;

use FilesystemIterator;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Walks a media directory exactly once and reports what is there.
 *
 * One walk, because this is the only expensive part of a scan: sizes, counts,
 * timestamps and the candidates that probes and the thumbnail generator will
 * need all come out of the same traversal.
 */
final class MediaInspector
{
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    /**
     * @param int $maxImageCandidates keeps the fact object bounded on media that
     *                                contain thousands of images; the thumbnail
     *                                generator only ever looks at the first few
     */
    public function __construct(private readonly int $maxImageCandidates = 50)
    {
    }

    public function inspect(string $mediaDir): MediaFacts
    {
        if (!is_dir($mediaDir)) {
            return new MediaFacts();
        }

        $sizeBytes = 0;
        $fileCount = 0;
        $mtime = (int) filemtime($mediaDir);
        $rootHtml = [];
        $rootFiles = [];
        $images = [];
        $prefixLength = strlen($mediaDir) + 1;

        foreach ($this->entries($mediaDir) as $file) {
            // Directories count towards mtime but nothing else: removing a file
            // only bumps its parent directory, so ignoring those would make a
            // deletion invisible to "last modified".
            $mtime = max($mtime, (int) $file->getMTime());

            if (!$file->isFile()) {
                continue;
            }

            $fileCount++;
            $size = $file->getSize() ?: 0;
            $sizeBytes += $size;

            $relative = substr($file->getPathname(), $prefixLength);
            $extension = strtolower($file->getExtension());

            if (!str_contains($relative, '/')) {
                $rootFiles[] = $file->getFilename();

                if ($extension === 'html' || $extension === 'htm') {
                    $rootHtml[] = $file->getFilename();
                }
            }

            if (in_array($extension, self::IMAGE_EXTENSIONS, true)) {
                $images[$relative] = $size;
            }
        }

        sort($rootHtml);
        sort($rootFiles);

        // Byte size is a cheap stand-in for "biggest picture": deciding on pixel
        // area would mean opening every candidate. Whoever generates the
        // thumbnail can refine among the first few.
        arsort($images);
        $images = array_slice(array_keys($images), 0, $this->maxImageCandidates);

        return new MediaFacts(
            sizeBytes: $sizeBytes,
            fileCount: $fileCount,
            mtime: $mtime,
            ctime: (int) filectime($mediaDir),
            rootHtml: $rootHtml,
            images: $images,
            rootFiles: $rootFiles,
        );
    }

    /**
     * Every entry in the subtree, directories included, skipping dot-entries and
     * never following symlinks — a link out of the content root would otherwise
     * be measured, or loop.
     *
     * @return iterable<SplFileInfo>
     */
    private function entries(string $directory): iterable
    {
        $directories = new RecursiveDirectoryIterator(
            $directory,
            FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_FILEINFO,
        );

        $filtered = new RecursiveCallbackFilterIterator(
            $directories,
            static fn (SplFileInfo $current): bool
                => !$current->isLink() && !str_starts_with($current->getFilename(), '.'),
        );

        foreach (new RecursiveIteratorIterator($filtered, RecursiveIteratorIterator::SELF_FIRST) as $file) {
            if ($file instanceof SplFileInfo) {
                yield $file;
            }
        }
    }
}
