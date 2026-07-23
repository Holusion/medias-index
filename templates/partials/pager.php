<?php

/**
 * @var \MediasIndex\View\View $this
 * @var \MediasIndex\View\Urls $urls
 * @var string $clientSlug
 * @var \MediasIndex\Storage\ProjectTotals $project
 * @var \MediasIndex\Storage\MediaPage $medias
 * @var int $page
 */

if ($medias->pageCount() <= 1) {
    return;
}
?>
<div class="toolbar">
    <?php if ($page > 1) { ?>
        <a class="btn btn-small btn-outline"
           href="<?= $this->e($urls->projectPage($clientSlug, $project->slug, $page - 1)) ?>">&larr;</a>
    <?php } ?>
    <span class="muted">page <?= $page ?> / <?= $medias->pageCount() ?></span>
    <?php if ($page < $medias->pageCount()) { ?>
        <a class="btn btn-small btn-outline"
           href="<?= $this->e($urls->projectPage($clientSlug, $project->slug, $page + 1)) ?>">&rarr;</a>
    <?php } ?>
</div>
