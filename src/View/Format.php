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
    private const UNITS = ['o', 'Kio', 'Mio', 'Gio', 'Tio'];

    /** Binary units, since that is what a filesystem actually reports. */
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
    public static function typeModifier(string $type): string
    {
        $slug = strtolower(trim($type));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';

        return trim($slug, '-') ?: 'unknown';
    }
}
