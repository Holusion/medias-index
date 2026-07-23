<?php

declare(strict_types=1);

namespace MediasIndex\Tests\View;

use MediasIndex\View\Embed;
use PHPUnit\Framework\TestCase;

final class EmbedTest extends TestCase
{
    public function testBuildsAnIframeAtTheConfiguredSize(): void
    {
        $code = (new Embed(640, 480))->code('https://example.com/files/a/b/c/', 'Salle bleue');

        self::assertSame(
            '<iframe src="https://example.com/files/a/b/c/" width="640" height="480"'
            . ' style="border:0" allowfullscreen title="Salle bleue"></iframe>',
            $code,
        );
    }

    /** frameborder is obsolete; the border has to be turned off with CSS. */
    public function testUsesCssRatherThanTheObsoleteFrameborderAttribute(): void
    {
        $code = (new Embed())->code('https://example.com/x/', 'x');

        self::assertStringContainsString('style="border:0"', $code);
        self::assertStringNotContainsString('frameborder', $code);
    }

    /**
     * The title comes from a media name, which comes from a folder name or a
     * manifest — so it can contain a quote and must not be able to close the
     * attribute and add its own.
     */
    public function testTitleCannotBreakOutOfTheAttribute(): void
    {
        $code = (new Embed())->code('https://example.com/x/', '" onload="alert(1)');

        // The payload survives as inert text — what matters is that its quotes
        // are escaped, so it cannot close the attribute and start a new one.
        self::assertSame(
            '<iframe src="https://example.com/x/" width="800" height="600" style="border:0"'
            . ' allowfullscreen title="&quot; onload=&quot;alert(1)"></iframe>',
            $code,
        );
    }

    public function testUrlIsEscapedToo(): void
    {
        $code = (new Embed())->code('https://example.com/a"b/', 'x');

        self::assertStringContainsString('src="https://example.com/a&quot;b/"', $code);
    }

    public function testAccentedTitlesSurviveIntact(): void
    {
        $code = (new Embed())->code('https://example.com/x/', 'Visite 360 — été');

        self::assertStringContainsString('title="Visite 360 — été"', $code);
    }
}
