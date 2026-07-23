<?php

/**
 * The page's own heading, and nothing above it.
 *
 * Ancestors belong to the sidebar's breadcrumb; stacking them here as a chain of
 * headings made every page read as though it were about its parent. The back
 * glyph is the one concession — it goes up exactly one level.
 *
 * @var \MediasIndex\View\View $this
 * @var string $title
 * @var string|null $back href of the parent, null at the root
 * @var string|null $backLabel what the back link is called, for screen readers
 */

$back ??= null;
$backLabel ??= 'Remonter';
?>
<h1 class="page-title">
    <?php if ($back !== null) { ?>
        <a class="page-title-back" href="<?= $this->e($back) ?>"
           aria-label="<?= $this->e($backLabel) ?>"><?= $this->render('partials/icon-back') ?></a>
    <?php } ?>
    <span><?= $this->e($title) ?></span>
</h1>
