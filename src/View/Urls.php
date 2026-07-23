<?php

declare(strict_types=1);

namespace MediasIndex\View;

/**
 * Builds every URL the pages emit.
 *
 * Centralised because the rules are easy to get subtly wrong and were already
 * duplicated: a media whose entry point is index.html links to its directory
 * (so the URL stays clean and Apache's DirectoryIndex serves it), while any
 * other entry point is named explicitly.
 *
 * `origin` is only needed by the absolute links and the embed snippet; relative
 * URLs are used everywhere else so the app does not break when reached through a
 * different host name.
 */
final readonly class Urls
{
    public function __construct(
        private string $filesBase,
        private string $thumbsBase,
        private string $origin = '',
    ) {
    }

    public function home(): string
    {
        return '/';
    }

    public function client(string $clientSlug): string
    {
        return '/c/' . rawurlencode($clientSlug);
    }

    public function project(string $clientSlug, string $projectSlug): string
    {
        return $this->client($clientSlug) . '/' . rawurlencode($projectSlug);
    }

    public function projectPage(string $clientSlug, string $projectSlug, int $page): string
    {
        return $this->project($clientSlug, $projectSlug) . '?page=' . $page;
    }

    /** A media's own page: its embed code, and a preview it can load on demand. */
    public function media(string $clientSlug, string $projectSlug, string $mediaSlug): string
    {
        return $this->project($clientSlug, $projectSlug) . '/' . rawurlencode($mediaSlug);
    }

    /** Directory of a media, always with a trailing slash. */
    public function mediaDirectory(string $clientSlug, string $projectSlug, string $mediaSlug): string
    {
        return $this->filesBase . '/'
            . rawurlencode($clientSlug) . '/'
            . rawurlencode($projectSlug) . '/'
            . rawurlencode($mediaSlug) . '/';
    }

    /**
     * Where a media's link points, or null when it has no browsable entry point.
     */
    public function content(
        string $clientSlug,
        string $projectSlug,
        string $mediaSlug,
        ?string $entryPath,
    ): ?string {
        if ($entryPath === null) {
            return null;
        }

        $directory = $this->mediaDirectory($clientSlug, $projectSlug, $mediaSlug);

        if ($entryPath === 'index.html') {
            return $directory;
        }

        // The entry path can contain directories; each segment is encoded, the
        // separators are not.
        return $directory . implode('/', array_map(rawurlencode(...), explode('/', $entryPath)));
    }

    /** Absolute form, for links meant to be copied elsewhere. */
    public function absolute(string $url): string
    {
        if ($this->origin === '' || preg_match('#^https?://#i', $url) === 1) {
            return $url;
        }

        return $this->origin . $url;
    }

    public function thumbnail(?string $file): ?string
    {
        return $file === null ? null : $this->thumbsBase . '/' . rawurlencode($file);
    }
}
