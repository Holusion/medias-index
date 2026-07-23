<?php

/**
 * One dialog per page, filled in by app.js from whatever opened it — rather than
 * one per media, which would repeat the whole thing 25 times.
 *
 * The preview frame is built on demand and thrown away when the dialog closes or
 * the toggle goes off: an iframe left in the document keeps loading, and for a
 * tour that is tens of megabytes.
 *
 * @var \MediasIndex\View\View $this
 */
?>
<dialog id="embed-dialog" class="dialog" aria-labelledby="embed-dialog-title">
    <form method="dialog" class="dialog-head">
        <h2 id="embed-dialog-title">Code d'intégration</h2>
        <button class="btn btn-small btn-outline" value="close" aria-label="Fermer">&times;</button>
    </form>

    <p class="muted" data-embed-subject></p>

    <label class="switch">
        <input type="checkbox" data-embed-preview-toggle>
        <span>Afficher l'aperçu</span>
    </label>

    <?php // Deliberately not sandboxed: the preview has to behave exactly like
          // the snippet it illustrates, and that snippet carries no sandbox. ?>
    <div class="dialog-preview" data-embed-preview hidden></div>

    <?php // tabindex makes the block focusable so it can be selected from the
          // keyboard; app.js selects it all on click. ?>
    <pre class="code-block" tabindex="0"><code data-embed-code></code></pre>

    <div class="toolbar">
        <button type="button" class="btn btn-primary" data-embed-copy>
            <span class="btn-label">Copier le code</span>
        </button>
        <form method="dialog"><button class="btn btn-outline" value="close">Fermer</button></form>
    </div>
</dialog>
