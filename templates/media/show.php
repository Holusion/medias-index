<?php

/**
 * A media's own page: what the embed dialog used to hold.
 *
 * The preview is always present but never loaded until asked for. The frame is
 * rigid — it holds the snippet's aspect ratio whether it contains the poster or
 * the iframe — so starting the preview swaps what is inside the box without the
 * page moving underneath the pointer.
 *
 * @var \MediasIndex\View\View $this
 * @var \MediasIndex\View\Urls $urls
 * @var \MediasIndex\View\Embed $embed
 * @var string $clientSlug
 * @var \MediasIndex\Storage\ProjectTotals $project
 * @var \MediasIndex\Storage\MediaRow $media
 */

use MediasIndex\View\Format;

$link = $urls->content($clientSlug, $project->slug, $media->slug, $media->entryPath);
$absolute = $link === null ? null : $urls->absolute($link);
$snippet = $absolute === null ? null : $embed->code($absolute, $media->name);
$thumbnail = $urls->thumbnail($media->thumbFile);
?>
<?= $this->render('partials/page-title', [
    'title' => $media->name,
    'back' => $urls->project($clientSlug, $project->slug),
    'backLabel' => 'Retour au projet ' . $project->name,
]) ?>

<p class="muted">
    <?= $this->render('partials/badge', ['type' => $media->type]) ?>
    <?= $this->e(Format::bytes($media->sizeBytes)) ?>
    · <?= $this->e(Format::plural($media->fileCount, 'fichier', 'fichiers', 'pas de fichier')) ?>
    · modifié le <?= $this->e(Format::dateTime($media->mtime)) ?>
    · créé le <?= $this->e(Format::dateTime($media->ctime)) ?>
</p>

<?php if ($absolute === null || $snippet === null) { ?>
    <div class="empty-state">
        Ce média n'a pas de point d'entrée exploitable : il ne peut être ni ouvert ni intégré.
    </div>
    <?php return;
} ?>

<div class="toolbar">
    <a class="btn btn-primary" href="<?= $this->e($link) ?>" target="_blank" rel="noopener">Ouvrir</a>
    <button type="button" class="btn btn-outline js-only" data-copy="<?= $this->e($absolute) ?>">
        <span class="btn-label">Copier le lien</span>
    </button>
</div>

<?php // max-width, so the preview shows the embed at the size it will really be
      // rather than stretched to whatever the column happens to be. ?>
<div class="preview" style="max-width: <?= (int) $embed->width ?>px">
    <div class="preview-frame" style="aspect-ratio: <?= (int) $embed->width ?> / <?= (int) $embed->height ?>"
         data-preview
         data-preview-src="<?= $this->e($link) ?>"
         data-preview-title="<?= $this->e($media->name) ?>">
        <?php if ($thumbnail !== null) { ?>
            <img class="preview-poster" src="<?= $this->e($thumbnail) ?>" alt="" width="400" height="300">
        <?php } else { ?>
            <span class="preview-poster is-empty"><?= $this->render('partials/icon-document') ?></span>
        <?php } ?>

        <?php // Hidden until app.js runs: without it there is nothing to load
              // the frame with, and "Ouvrir" is the way through. ?>
        <button type="button" class="preview-play" data-preview-start
                aria-label="Lancer l'aperçu de <?= $this->e($media->name) ?>">
            <?= $this->render('partials/icon-play') ?>
        </button>
    </div>
</div>

<h2>Code d'intégration</h2>

<pre class="code-block" tabindex="0"><code data-embed-code><?= $this->e($snippet) ?></code></pre>

<div class="toolbar">
    <button type="button" class="btn btn-primary js-only" data-embed-copy>
        <span class="btn-label">Copier le code</span>
    </button>
</div>
