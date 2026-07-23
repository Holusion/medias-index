<?php

declare(strict_types=1);

namespace MediasIndex\Tests\View;

use MediasIndex\View\Format;
use PHPUnit\Framework\TestCase;

final class FormatTest extends TestCase
{
    public function testBytesUsesBinaryUnits(): void
    {
        self::assertSame('0 o', Format::bytes(0));
        self::assertSame('999 o', Format::bytes(999));
        self::assertSame('1,0 Kio', Format::bytes(1024));
        self::assertSame('1,5 Kio', Format::bytes(1536));
        self::assertSame('1,0 Mio', Format::bytes(1024 * 1024));
    }

    /** Above 100 the decimal is noise, and it is what makes tiles wrap. */
    public function testBytesDropsTheDecimalOnLargeFigures(): void
    {
        self::assertSame('500 Kio', Format::bytes(512_000));
    }

    public function testBytesNeverGoesNegative(): void
    {
        self::assertSame('0 o', Format::bytes(-5));
    }

    public function testZeroTimestampsRenderAsADash(): void
    {
        self::assertSame('—', Format::dateTime(0));
        self::assertSame('—', Format::day(0));
    }

    public function testTypeModifierIsAlwaysASafeCssSlug(): void
    {
        self::assertSame('krpano', Format::typeModifier('krpano'));
        self::assertSame('my-type', Format::typeModifier('My Type'));
        self::assertSame('unknown', Format::typeModifier(''));
        self::assertSame('unknown', Format::typeModifier('!!!'));
    }

    /**
     * The type comes from the database and could be written by a newer build, so
     * it must never be able to break out of the class attribute.
     */
    public function testTypeModifierStripsAnythingThatCouldEscapeTheAttribute(): void
    {
        self::assertSame('x-onerror-alert-1', Format::typeModifier('x" onerror="alert(1)'));
    }
}
