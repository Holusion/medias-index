<?php

declare(strict_types=1);

namespace MediasIndex\Indexer;

use InvalidArgumentException;

/**
 * Navigates the three fixed levels below the content root: client / project /
 * media. Knows nothing about what a media contains — that is MediaInspector's
 * job — only which directories count as one.
 *
 * Rules, from docs/DESIGN.md §2: only directories, anything starting with a dot
 * is ignored, configured names are ignored, symlinks are not followed.
 */
final class Tree
{
    /** @param list<string> $ignore directory names skipped at every level */
    public function __construct(
        private readonly string $root,
        private readonly array $ignore = [],
    ) {
    }

    /** @return list<string> */
    public function clients(): array
    {
        return $this->subdirectories($this->root);
    }

    /** @return list<string> */
    public function projects(string $client): array
    {
        return $this->subdirectories($this->path($client));
    }

    /** @return list<string> */
    public function medias(string $client, string $project): array
    {
        return $this->subdirectories($this->path($client, $project));
    }

    /**
     * Absolute path of a tree node.
     *
     * Segments reach this from URLs, so they are validated rather than trusted:
     * a slug containing a separator or a parent reference would otherwise walk
     * out of the content root.
     */
    public function path(string ...$segments): string
    {
        foreach ($segments as $segment) {
            if (!self::isValidSegment($segment)) {
                throw new InvalidArgumentException(sprintf('Invalid path segment "%s".', $segment));
            }
        }

        return rtrim($this->root . '/' . implode('/', $segments), '/');
    }

    public static function isValidSegment(string $segment): bool
    {
        return $segment !== ''
            && !str_contains($segment, '/')
            && !str_contains($segment, '\\')
            && !str_starts_with($segment, '.');
    }

    /** @return list<string> sorted, so listings are stable between scans */
    private function subdirectories(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $names = [];

        foreach (scandir($directory) ?: [] as $name) {
            $path = $directory . '/' . $name;

            if (
                !self::isValidSegment($name)
                || in_array($name, $this->ignore, true)
                || is_link($path)
                || !is_dir($path)
            ) {
                continue;
            }

            $names[] = $name;
        }

        sort($names);

        return $names;
    }
}
