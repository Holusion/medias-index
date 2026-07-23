<?php

/**
 * @var \MediasIndex\View\View $this
 * @var \MediasIndex\View\Urls $urls
 * @var \MediasIndex\View\Embed $embed
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

        // Absolute: both the copied link and the snippet are meant to be used
        // away from this page.
        $absolute = $link === null ? null : $urls->absolute($link);
        $snippet = $absolute === null ? null : $embed->code($absolute, $media->name);
        ?>
        <li>
            <div class="media">
                <?php
                // The thumbnail opens the same dialog as the button beside it,
                // but with the preview already on — clicking a picture asks to
                // see the thing, not to read markup about it. A real <button>
                // rather than a clickable div, so it is reachable by keyboard
                // and announced as an action.
                $thumbTag = $snippet === null ? 'div' : 'button';
                $thumbClass = 'media-thumb' . ($thumbnail === null ? ' is-empty' : '');
                ?>
                <<?= $thumbTag ?> class="<?= $thumbClass ?>"
                    <?php if ($snippet !== null) { ?>
                        type="button" data-preview
                        data-embed="<?= $this->e($snippet) ?>"
                        data-embed-title="<?= $this->e($media->name) ?>"
                        data-embed-src="<?= $this->e($absolute) ?>"
                        data-embed-width="<?= (int) $embed->width ?>"
                        data-embed-height="<?= (int) $embed->height ?>"
                        aria-label="Aperçu de <?= $this->e($media->name) ?>"
                    <?php } ?>>
                    <?php if ($thumbnail !== null) { ?>
                        <img src="<?= $this->e($thumbnail) ?>" alt="" loading="lazy" width="400" height="300">
                    <?php } else { ?>
                        <?= $this->render('partials/icon-document') ?>
                    <?php } ?>
                </<?= $thumbTag ?>>
                <div class="media-body">
                    <div class="media-title">
                        <span><?= $this->e($media->name) ?></span>
                        <?= $this->render('partials/badge', ['type' => $media->type]) ?>
                    </div>
                    <div class="media-meta">
                        <?= $this->e(Format::bytes($media->sizeBytes)) ?>
                        · <?= $this->e(Format::plural($media->fileCount, 'fichier', 'fichiers', 'pas de fichier')) ?>
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

                <?php if ($absolute !== null && $snippet !== null) { ?>
                    <?php // Hidden until app.js runs: a button that cannot work
                          // without script has no business being visible. ?>
                    <div class="media-actions">
                        <?php // The label is wrapped so it can be hidden while
                              // still holding the button's width open when the
                              // "copied" message is laid over it. ?>
                        <button type="button" class="btn btn-small btn-outline"
                                data-copy="<?= $this->e($absolute) ?>">
                            <span class="btn-label">Copier le lien</span>
                        </button>
                        <?php // No data-preview: opened this way the dialog is
                              // about the markup, and the preview is a toggle. ?>
                        <button type="button" class="btn btn-small btn-outline"
                                data-embed="<?= $this->e($snippet) ?>"
                                data-embed-title="<?= $this->e($media->name) ?>"
                                data-embed-src="<?= $this->e($absolute) ?>"
                                data-embed-width="<?= (int) $embed->width ?>"
                                data-embed-height="<?= (int) $embed->height ?>">Code d'intégration</button>
                    </div>
                <?php } ?>
            </div>
        </li>
    <?php } ?>
</ul>
