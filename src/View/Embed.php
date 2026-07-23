<?php

declare(strict_types=1);

namespace MediasIndex\View;

/**
 * Builds the `<iframe>` snippet a user copies into their own page.
 *
 * The result is HTML *source* meant to be read and pasted, so attribute values
 * are escaped here for the markup being generated; whatever displays it escapes
 * again for the page it appears on.
 */
final readonly class Embed
{
    /** Public because the preview reuses them to show the real proportions. */
    public function __construct(
        public int $width = 800,
        public int $height = 600,
    ) {
    }

    /**
     * @param string $url   absolute — a relative src would break the moment the
     *                      snippet is pasted anywhere but this site
     * @param string $title names the frame for screen readers, and is what a
     *                      browser shows if the content fails to load
     */
    public function code(string $url, string $title): string
    {
        return sprintf(
            // style="border:0" rather than frameborder, which is obsolete.
            '<iframe src="%s" width="%d" height="%d" style="border:0" allowfullscreen title="%s"></iframe>',
            self::attribute($url),
            $this->width,
            $this->height,
            self::attribute($title),
        );
    }

    private static function attribute(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
