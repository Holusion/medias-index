<?php

/**
 * Inline SVG rather than an icon font or an image: it inherits currentColor, so
 * it follows the link's hover and focus states without a second rule, and costs
 * no extra request.
 *
 * aria-hidden because whatever wraps it carries the accessible name.
 *
 * @var \MediasIndex\View\View $this
 */
?>
<svg class="icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
    <path d="M12 3 2 12h3v9h6v-6h2v6h6v-9h3z"/>
</svg>
