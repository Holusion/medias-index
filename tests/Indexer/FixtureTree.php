<?php

declare(strict_types=1);

namespace MediasIndex\Tests\Indexer;

/**
 * Builds a throwaway content tree in the system temp directory.
 *
 * The tests deliberately do not use dev/sample-files.php: files/ is gitignored,
 * so it does not exist on a fresh checkout or in CI.
 */
trait FixtureTree
{
    private ?string $fixtureRoot = null;

    protected function tree(): string
    {
        return $this->fixtureRoot ??= (static function (): string {
            $path = sys_get_temp_dir() . '/medias-index-test-' . bin2hex(random_bytes(6));
            mkdir($path, 0o775, true);

            return $path;
        })();
    }

    protected function writeFile(string $relative, string $contents = 'x'): string
    {
        $path = $this->tree() . '/' . $relative;

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0o775, true);
        }

        file_put_contents($path, $contents);

        return $path;
    }

    protected function makeDirectory(string $relative): string
    {
        $path = $this->tree() . '/' . $relative;

        if (!is_dir($path)) {
            mkdir($path, 0o775, true);
        }

        return $path;
    }

    protected function removeTree(): void
    {
        if ($this->fixtureRoot === null || !is_dir($this->fixtureRoot)) {
            return;
        }

        $entries = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->fixtureRoot, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($entries as $entry) {
            $entry->isDir() && !$entry->isLink() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }

        rmdir($this->fixtureRoot);
        $this->fixtureRoot = null;
    }
}
