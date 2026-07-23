<?php

declare(strict_types=1);

namespace MediasIndex\View;

/**
 * Presentation of the raw figures the database stores.
 *
 * Pure functions, kept out of the templates so they can be tested directly.
 */
final class Format
{
    /**
     * Ko/Mo/Go rather than the strict Kio/Mio/Gio, because that is what people
     * read everywhere else.
     *
     * Note the steps below stay at 1024, so these labels are the customary ones
     * rather than the literally correct ones — a "Ko" here is 1024 octets, as it
     * is in most file managers. Switching to 1000 would make the labels exact and
     * every displayed figure change.
     */
    private const UNITS = ['o', 'Ko', 'Mo', 'Go', 'To'];

    /** Labels for the machine-readable values stored in medias.type. */
    private const TYPE_LABELS = ['unknown' => 'inconnu'];

    public static function bytes(int $bytes): string
    {
        $index = 0;
        $value = (float) max(0, $bytes);

        while ($value >= 1024 && $index < count(self::UNITS) - 1) {
            $value /= 1024;
            $index++;
        }

        $decimals = ($index === 0 || $value >= 100) ? 0 : 1;

        return number_format($value, $decimals, ',', ' ') . ' ' . self::UNITS[$index];
    }

    /** Grouped thousands, so a file count of 1648 reads as "1 648". */
    public static function number(int $value): string
    {
        return number_format($value, 0, ',', ' ');
    }

    /**
     * A count with its noun agreed.
     *
     * French pluralises differently from English, and the difference is exactly
     * at the value most likely to be tested last: CLDR puts **0 in the singular**
     * — "0 projet", not "0 projets". So the rule is `< 2`, not `=== 1`. Writing
     * the English rule here would be wrong in a way that only shows on empty
     * data.
     *
     * $zero replaces the whole phrase when there is nothing, because "pas de
     * projet" reads better than "0 projet". It is passed in rather than built
     * from the singular: "pas de" elides to "pas d'" before a vowel, and
     * guessing that is how you end up with "pas de image".
     *
     * This is the one plural rule the interface needs. Should it ever need
     * several languages, ext-intl's MessageFormatter is the replacement —
     * `{n, plural, =0{…} one{# projet} other{# projets}}` — and the call sites
     * already carry every form it would want.
     */
    public static function plural(int $count, string $singular, string $plural, ?string $zero = null): string
    {
        if ($count === 0 && $zero !== null) {
            return $zero;
        }

        return self::number($count) . ' ' . (abs($count) < 2 ? $singular : $plural);
    }

    /** Filesystem timestamps are unix seconds; 0 means the scan found none. */
    public static function dateTime(int $timestamp): string
    {
        return $timestamp > 0 ? date('d/m/Y H:i', $timestamp) : '—';
    }

    /** Day only: at stat-tile size the time wraps to a second line for no gain. */
    public static function day(int $timestamp): string
    {
        return $timestamp > 0 ? date('d/m/Y', $timestamp) : '—';
    }

    /**
     * CSS modifier for a media type.
     *
     * The type comes from the database and may be one this build has never heard
     * of, so it is reduced to a safe slug and the stylesheet falls back to the
     * neutral badge.
     */
    /**
     * What a media type is called on screen.
     *
     * The stored value stays a machine identifier — it keys the CSS modifier and
     * is what queries filter on — so only the display passes through here. Types
     * with no entry show as they are, which is what "html" and "krpano" want
     * anyway, and keeps a type written by a newer build readable.
     */
    public static function typeLabel(string $type): string
    {
        return self::TYPE_LABELS[$type] ?? $type;
    }

    public static function typeModifier(string $type): string
    {
        $slug = strtolower(trim($type));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';

        return trim($slug, '-') ?: 'unknown';
    }
}
