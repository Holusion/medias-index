<?php

declare(strict_types=1);

namespace MediasIndex\Indexer;

/**
 * The media types a probe can assign, stored in medias.type.
 *
 * Deliberately a plain list of string constants rather than an enum: the column
 * is a VARCHAR so that adding a type is a new probe and nothing else — no
 * migration, and no crash when a row written by a newer version is read by an
 * older one.
 */
final class MediaType
{
    /** Nothing recognisable: indexed and listed, but not browsable. */
    public const UNKNOWN = 'unknown';

    /** A browsable page was found, without any more specific structure. */
    public const HTML = 'html';

    /** A krpano virtual tour, identified and described by its tour.xml. */
    public const KRPANO = 'krpano';
}
