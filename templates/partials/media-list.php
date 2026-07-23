<?php

/**
 * The medias of a project.
 *
 * The whole card is one link to the media's page. The copy button sits outside
 * it, in its own column: a link inside a link is invalid and unpredictable, so
 * the button's column is simply not part of the anchor.
 *
 * That is also why the entry-point filename here is text rather than a link —
 * opening the content is what the media's own page is for.
 *
 * @var \MediasIndex\View\View $this
 * @var \MediasIndex\View\Urls $urls
 * @var string $clientSlug
 * @var \MediasIndex\Storage\ProjectTotals $project
 * @var \MediasIndex\Storage\MediaPage $medias
 */

use MediasIndex\View\Format;

?>
<ul class="media-list">
    <?php foreach ($medias->items as $media) {
        $page = $urls->media($clientSlug, $project->slug, $media->slug);
        $link = $urls->content($clientSlug, $project->slug, $media->slug, $media->entryPath);
        $absolute = $link === null ? null : $urls->absolute($link);
        $thumbnail = $urls->thumbnail($media->thumbFile);
        ?>
        <li class="media">
            <a class="media-link" href="<?= $this->e($page) ?>">
                <span class="media-thumb<?= $thumbnail === null ? ' is-empty' : '' ?>">
                    <?php if ($thumbnail !== null) { ?>
                        <img src="<?= $this->e($thumbnail) ?>" alt="" loading="lazy" width="400" height="300">
                    <?php } else { ?>
                        <?= $this->render('partials/icon-document') ?>
                    <?php } ?>
                </span>
                <span class="media-body">
                    <span class="media-title">
                        <span><?= $this->e($media->name) ?></span>
                        <?= $this->render('partials/badge', ['type' => $media->type]) ?>
                    </span>
                    <span class="media-meta">
                        <?= $this->e(Format::bytes($media->sizeBytes)) ?>
                        · <?= $this->e(Format::plural($media->fileCount, 'fichier', 'fichiers', 'pas de fichier')) ?>
                        · modifié le <?= $this->e(Format::dateTime($media->mtime)) ?>
                        · <?= $media->isUsable() ? $this->e($media->entryPath) : "aucun point d'entrée" ?>
                    </span>
                </span>
            </a>

            <div class="media-actions">
                <?php if ($absolute !== null) { ?>
                    <button type="button" class="btn btn-small btn-outline js-only"
                            data-copy="<?= $this->e($absolute) ?>">
                        <span class="btn-label">Copier le lien</span>
                    </button>
                <?php } ?>
            </div>
        </li>
    <?php } ?>
</ul>
