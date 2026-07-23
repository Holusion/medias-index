<?php

declare(strict_types=1);

namespace MediasIndex\Indexer;

use GdImage;

/**
 * Produces a uniform JPEG thumbnail from whatever image a probe nominated.
 *
 * GD only: Imagick is unreliable on OVH shared hosting, and GD is always there.
 *
 * Thumbnails live outside the deployed repository (see docs/DESIGN.md §4), so a
 * release never wipes the cache. The filename embeds a digest of the source path
 * and its mtime and size, which means a changed source produces a new filename
 * and can never be served from a stale cache entry.
 */
final class ThumbnailGenerator
{
    /**
     * Refuse sources large enough to exhaust memory before GD even starts.
     *
     * A decoded image costs roughly 4 bytes per pixel, so 40 MP is about 160 MB
     * — already uncomfortable against the 512 MB the host allows, and far beyond
     * anything that belongs in a 400×300 thumbnail.
     */
    private const MAX_SOURCE_PIXELS = 40_000_000;

    public function __construct(
        private readonly string $thumbsDir,
        private readonly int $width = 400,
        private readonly int $height = 300,
        private readonly int $quality = 80,
    ) {
    }

    /**
     * @param  string $sourcePath absolute path to the image the probe nominated
     * @return string|null        filename inside the thumbnails directory, or null
     *                            if nothing usable could be produced
     */
    public function generate(int $mediaId, string $sourcePath): ?string
    {
        if (!is_file($sourcePath) || !is_readable($sourcePath)) {
            return null;
        }

        $info = @getimagesize($sourcePath);

        if ($info === false || $info[0] < 1 || $info[1] < 1) {
            return null;
        }

        if ($info[0] * $info[1] > self::MAX_SOURCE_PIXELS) {
            return null;
        }

        $filename = $this->filenameFor($mediaId, $sourcePath);
        $target = $this->thumbsDir . '/' . $filename;

        if (is_file($target)) {
            return $filename;
        }

        if (!is_dir($this->thumbsDir) && !@mkdir($this->thumbsDir, 0o775, true) && !is_dir($this->thumbsDir)) {
            return null;
        }

        $source = $this->load($sourcePath, (int) $info[2]);

        if ($source === null) {
            return null;
        }

        $thumbnail = $this->resize($source, $info[0], $info[1]);
        imagedestroy($source);

        $written = @imagejpeg($thumbnail, $target, $this->quality);
        imagedestroy($thumbnail);

        if (!$written) {
            return null;
        }

        $this->discardPreviousVersions($mediaId, $filename);

        return $filename;
    }

    /** Removes thumbnails this media left behind under earlier digests. */
    public function discardPreviousVersions(int $mediaId, ?string $keep = null): void
    {
        foreach (glob($this->thumbsDir . '/' . $mediaId . '-*.jpg') ?: [] as $path) {
            if ($keep === null || basename($path) !== $keep) {
                @unlink($path);
            }
        }
    }

    private function filenameFor(int $mediaId, string $sourcePath): string
    {
        $digest = substr(
            sha1(sprintf(
                '%s|%d|%d|%dx%d|%d',
                $sourcePath,
                (int) @filemtime($sourcePath),
                (int) @filesize($sourcePath),
                $this->width,
                $this->height,
                $this->quality,
            )),
            0,
            12,
        );

        return $mediaId . '-' . $digest . '.jpg';
    }

    private function load(string $path, int $imageType): ?GdImage
    {
        $image = match ($imageType) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_GIF => @imagecreatefromgif($path),
            IMAGETYPE_WEBP => @imagecreatefromwebp($path),
            default => false,
        };

        return $image === false ? null : $image;
    }

    /**
     * Scales to cover the target box and crops the overflow, so every card in a
     * listing gets an image of exactly the same shape. Letterboxing would keep
     * more of the picture but make the list ragged.
     */
    private function resize(GdImage $source, int $sourceWidth, int $sourceHeight): GdImage
    {
        $scale = max($this->width / $sourceWidth, $this->height / $sourceHeight);
        $cropWidth = min($sourceWidth, (int) round($this->width / $scale));
        $cropHeight = min($sourceHeight, (int) round($this->height / $scale));

        $thumbnail = imagecreatetruecolor($this->width, $this->height);

        imagecopyresampled(
            $thumbnail,
            $source,
            0,
            0,
            (int) round(($sourceWidth - $cropWidth) / 2),
            (int) round(($sourceHeight - $cropHeight) / 2),
            $this->width,
            $this->height,
            $cropWidth,
            $cropHeight,
        );

        return $thumbnail;
    }
}
