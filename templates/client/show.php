<?php

/**
 * @var \MediasIndex\View\View $this
 * @var \MediasIndex\View\Urls $urls
 * @var string $clientSlug
 * @var \MediasIndex\Storage\ClientTotals|null $clientTotals
 * @var \MediasIndex\Storage\ProjectTotals|null $selected
 * @var \MediasIndex\Storage\MediaPage|null $medias
 * @var int $page
 */

use MediasIndex\View\Format;

?>
<h1><?= $this->e($clientSlug) ?></h1>

<?php if ($selected === null || $medias === null) { ?>

    <?php if ($clientTotals !== null) {
        echo $this->render('partials/stats', [
            'figures' => [
                'Taille' => Format::bytes($clientTotals->sizeBytes),
                'Projets' => Format::number($clientTotals->projectCount),
                'Médias' => Format::number($clientTotals->mediaCount),
                'Créé le' => Format::day($clientTotals->ctime),
                'Modifié le' => Format::day($clientTotals->mtime),
            ],
        ]);
    }?>
    <div class="empty-state">Sélectionnez un projet.</div>
    <?php 
    return;
} ?>

<a class="btn btn-small btn-outline sidebar-back"
   href="<?= $this->e($urls->client($clientSlug)) ?>">&larr; Projets</a>

<h2><?= $this->e($selected->name) ?></h2>

<p class="muted">
    <?= $this->e(Format::plural($medias->total, 'média', 'médias', 'pas de média')) ?>
    · <?= $this->e(Format::bytes($selected->sizeBytes)) ?> ·
    modifié le <?= $this->e(Format::dateTime($selected->mtime)) ?>
    <?php foreach ($selected->types as $type) { ?>
        <?= $this->render('partials/badge', ['type' => $type]) ?>
    <?php } ?>
</p>

<?= $this->render('partials/media-list', [
    'clientSlug' => $clientSlug,
    'project' => $selected,
    'medias' => $medias,
]) ?>

<?= $this->render('partials/pager', [
    'clientSlug' => $clientSlug,
    'project' => $selected,
    'medias' => $medias,
    'page' => $page,
]) ?>

<?= $this->render('partials/embed-dialog') ?>
