<?php

/**
 * @var \MediasIndex\View\View $this
 * @var \MediasIndex\View\Urls $urls
 * @var string $clientSlug
 * @var \MediasIndex\Storage\ProjectTotals $project
 * @var \MediasIndex\Storage\MediaPage $medias
 * @var int $page
 */

use MediasIndex\View\Format;

?>
<?= $this->render('partials/page-title', [
    'title' => $project->name,
    'back' => $urls->client($clientSlug),
    'backLabel' => 'Retour au client ' . $clientSlug,
]) ?>

<p class="muted">
    <?= $this->e(Format::plural($medias->total, 'média', 'médias', 'pas de média')) ?>
    · <?= $this->e(Format::bytes($project->sizeBytes)) ?>
    · modifié le <?= $this->e(Format::dateTime($project->mtime)) ?>
    <?php foreach ($project->types as $type) { ?>
        <?= $this->render('partials/badge', ['type' => $type]) ?>
    <?php } ?>
</p>

<?= $this->render('partials/media-list', [
    'clientSlug' => $clientSlug,
    'project' => $project,
    'medias' => $medias,
]) ?>

<?= $this->render('partials/pager', [
    'clientSlug' => $clientSlug,
    'project' => $project,
    'medias' => $medias,
    'page' => $page,
]) ?>
