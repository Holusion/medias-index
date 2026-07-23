<?php

declare(strict_types=1);

namespace MediasIndex\Support;

use RuntimeException;

/**
 * Immutable application configuration, loaded from a PHP file returning an array.
 *
 * The file holds database credentials and the hook secret, so it must live
 * outside version control and outside anything a deployment can overwrite or
 * delete. Rather than mandate one location, candidates are tried in order of
 * decreasing safety and the first existing one wins — see candidatePaths().
 */
final class Config
{
    /** @param array<string, mixed> $values */
    public function __construct(
        private readonly array $values,
        private readonly ?string $sourcePath = null,
    ) {
    }

    public static function load(?string $path = null): self
    {
        if ($path === null) {
            foreach (self::candidatePaths() as $candidate) {
                if (is_file($candidate)) {
                    $path = $candidate;
                    break;
                }
            }
        }

        if ($path === null) {
            throw new RuntimeException(sprintf(
                "No configuration file found. Copy config/config.example.php to one of:\n  - %s",
                implode("\n  - ", self::candidatePaths()),
            ));
        }

        if (!is_file($path)) {
            throw new RuntimeException(sprintf('Configuration file "%s" not found.', $path));
        }

        $values = require $path;

        if (!is_array($values)) {
            throw new RuntimeException(sprintf('Configuration file "%s" must return an array.', $path));
        }

        return new self($values, $path);
    }

    /**
     * Where load() looks, best first. Documented in the README; bin/doctor.php
     * reports which one was actually used.
     *
     * 1. MEDIAS_INDEX_CONFIG      explicit override (docker, and .htaccess SetEnv)
     * 2. <home>/config/...        outside the document root: no URL can reach it
     * 3. <www>/config.php         inside the document root, denied in .htaccess,
     *                             but still outside the deployed repository
     * 4. <app>/config/config.php  local development only — a mirroring deploy
     *                             would delete it, since it is gitignored
     *
     * @return list<string>
     */
    public static function candidatePaths(): array
    {
        $app = self::rootDir();
        $paths = [];

        if ($fromEnv = getenv('MEDIAS_INDEX_CONFIG')) {
            $paths[] = $fromEnv;
        }

        return [
            ...$paths,
            dirname($app, 2) . '/config/medias-index.php',
            dirname($app) . '/config.php',
            $app . '/config/config.php',
        ];
    }

    /** Absolute path of the file this configuration came from, if any. */
    public function sourcePath(): ?string
    {
        return $this->sourcePath;
    }

    /** Absolute path to the repository root. */
    public static function rootDir(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * Read a value using dotted notation, e.g. get('db.dsn').
     *
     * Passing no default makes the key mandatory.
     */
    public function get(string $key, mixed $default = null, bool $required = true): mixed
    {
        $value = $this->values;

        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                if ($default === null && $required) {
                    throw new RuntimeException(sprintf('Missing configuration key "%s".', $key));
                }

                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    public function string(string $key, ?string $default = null): string
    {
        return (string) $this->get($key, $default, $default === null);
    }

    public function int(string $key, ?int $default = null): int
    {
        return (int) $this->get($key, $default, $default === null);
    }

    /** @return array<int|string, mixed> */
    public function array(string $key, array $default = []): array
    {
        $value = $this->get($key, $default, false);

        return is_array($value) ? $value : $default;
    }

    /** Absolute path with any trailing slash removed. */
    public function path(string $key): string
    {
        return rtrim($this->string($key), '/');
    }

    /** URL prefix with any trailing slash removed. */
    public function url(string $key): string
    {
        return rtrim($this->string($key, ''), '/');
    }
}
