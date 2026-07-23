<?php

/**
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
        $link = $urls->content($clientSlug, $project->slug, $media->slug, $media->entryPath);
        $thumbnail = $urls->thumbnail($media->thumbFile);
        ?>
        <li>
            <div class="media">
                <div class="media-thumb">
                    <?php if ($thumbnail !== null) { ?>
                        <img src="<?= $this->e($thumbnail) ?>" alt="" loading="lazy" width="400" height="300">
                    <?php } ?>
                </div>
                <div class="media-body">
                    <div class="media-title">
                        <span><?= $this->e($media->name) ?></span>
                        <?= $this->render('partials/badge', ['type' => $media->type]) ?>
                    </div>
                    <div class="media-meta">
                        <?= $this->e(Format::bytes($media->sizeBytes)) ?>
                        · <?= $media->fileCount ?> fichier(s)
                        · modifié le <?= $this->e(Format::dateTime($media->mtime)) ?>
                        <?php if ($link !== null) { ?>
                            · <a href="<?= $this->e($link) ?>" target="_blank" rel="noopener">
                                <?= $this->e($media->entryPath) ?>
                            </a>
                        <?php } else { ?>
                            · aucun point d'entrée
                        <?php } ?>
                    </div>
                </div>
            </div>
        </li>
    <?php } ?>
</ul>
