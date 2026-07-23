<?php

declare(strict_types=1);

namespace MediasIndex\Tests\View;

use MediasIndex\View\Format;
use PHPUnit\Framework\TestCase;

final class FormatTest extends TestCase
{
    public function testBytesUsesCustomaryFrenchUnits(): void
    {
        self::assertSame('0 o', Format::bytes(0));
        self::assertSame('999 o', Format::bytes(999));
        self::assertSame('1,0 Ko', Format::bytes(1024));
        self::assertSame('1,5 Ko', Format::bytes(1536));
        self::assertSame('1,0 Mo', Format::bytes(1024 * 1024));
    }

    /** Above 100 the decimal is noise, and it is what makes tiles wrap. */
    public function testBytesDropsTheDecimalOnLargeFigures(): void
    {
        self::assertSame('500 Ko', Format::bytes(512_000));
    }

    public function testBytesNeverGoesNegative(): void
    {
        self::assertSame('0 o', Format::bytes(-5));
    }

    public function testCountsAreGrouped(): void
    {
        self::assertSame('0', Format::number(0));
        self::assertSame('999', Format::number(999));
        self::assertSame('1 648', Format::number(1648));
    }

    /**
     * The rule French gets wrong when copied from English: CLDR puts 0 in the
     * singular, so it is "0 projet" — never "0 projets".
     */
    public function testZeroTakesTheSingularWhenNoZeroFormIsGiven(): void
    {
        self::assertSame('0 projet', Format::plural(0, 'projet', 'projets'));
    }

    public function testTheZeroFormReplacesTheWholePhrase(): void
    {
        self::assertSame('pas de projet', Format::plural(0, 'projet', 'projets', 'pas de projet'));
    }

    public function testOneIsSingularAndTwoIsPlural(): void
    {
        self::assertSame('1 projet', Format::plural(1, 'projet', 'projets', 'pas de projet'));
        self::assertSame('2 projets', Format::plural(2, 'projet', 'projets', 'pas de projet'));
    }

    public function testLargeCountsAreGroupedAndPlural(): void
    {
        self::assertSame('1 648 fichiers', Format::plural(1648, 'fichier', 'fichiers'));
    }

    public function testZeroTimestampsRenderAsADash(): void
    {
        self::assertSame('—', Format::dateTime(0));
        self::assertSame('—', Format::day(0));
    }

    /**
     * The stored type is a machine identifier; only its display is translated,
     * so the CSS modifier and any query keep working off the raw value.
     */
    public function testKnownTypesGetAFrenchLabel(): void
    {
        self::assertSame('inconnu', Format::typeLabel('unknown'));
        self::assertSame('unknown', Format::typeModifier('unknown'));
    }

    public function testTypesWithoutATranslationShowAsThemselves(): void
    {
        self::assertSame('html', Format::typeLabel('html'));
        self::assertSame('krpano', Format::typeLabel('krpano'));
        self::assertSame('voyager', Format::typeLabel('voyager'), 'a type from a newer build stays readable');
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
