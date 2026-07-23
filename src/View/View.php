<?php

declare(strict_types=1);

namespace MediasIndex\View;

use RuntimeException;
use Throwable;

/**
 * Plain PHP templates.
 *
 * No template engine: the pages are simple, and a dependency that has to be
 * carried onto shared hosting has to earn its place. What matters is that
 * templates are files rather than strings inside controllers, and that escaping
 * is one short call away.
 *
 * Inside a template, $this is the View, so `$this->e(...)` escapes and
 * `$this->render(...)` includes a partial.
 */
final class View
{
    /**
     * @param array<string, mixed> $shared variables every template receives, for
     *                                     the few things they all need (the URL
     *                                     builder) without reaching for globals
     */
    public function __construct(
        private readonly string $templateDir,
        private readonly array $shared = [],
    ) {
    }

    /**
     * @param  array<string, mixed> $data becomes local variables in the template
     */
    public function render(string $template, array $data = []): string
    {
        $data = [...$this->shared, ...$data];

        $path = $this->templateDir . '/' . $template . '.php';

        if (!is_file($path)) {
            throw new RuntimeException(sprintf('Template "%s" not found at %s.', $template, $path));
        }

        $level = ob_get_level();
        ob_start();

        try {
            (function () use ($path, $data): void {
                extract($data, EXTR_SKIP);
                require $path;
            })();

            return (string) ob_get_clean();
        } catch (Throwable $e) {
            // Without this, a failure halfway through a template would leave its
            // partial output in the buffer and surface as a garbled page.
            while (ob_get_level() > $level) {
                ob_end_clean();
            }

            throw $e;
        }
    }

    /** Escape for HTML text and attribute values. */
    public function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
