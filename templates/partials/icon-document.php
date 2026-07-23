<?php

/**
 * Placeholder for a media with no thumbnail.
 *
 * Drawn rather than typed. U+1F5CE 🗎 does render where a font carries it, but
 * what it looks like is the font's decision: monochrome on one machine, a
 * full-colour emoji on the next — and a colour emoji ignores `color`, so it
 * cannot be muted to sit quietly in a placeholder. An inline SVG looks the same
 * everywhere, follows currentColor, and costs no request.
 *
 * Outline rather than solid, so it reads as an absence rather than as content.
 *
 * @var \MediasIndex\View\View $this
 */
?>
<svg class="icon-document" viewBox="0 0 24 24" aria-hidden="true" focusable="false"
     fill="none" stroke="currentColor" stroke-width="1.4"
     stroke-linecap="round" stroke-linejoin="round">
    <path d="M13.5 3H7A1.5 1.5 0 0 0 5.5 4.5v15A1.5 1.5 0 0 0 7 21h10a1.5 1.5 0 0 0 1.5-1.5V8z"/>
    <path d="M13.5 3v5h5"/>
</svg>
