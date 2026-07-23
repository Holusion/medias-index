<?php

/**
 * One level deeper than the project sidebar: the medias of the project the
 * crumbs name, so a media's page lets you step sideways to its siblings.
 *
 * @var \MediasIndex\View\View $this
 * @var \MediasIndex\View\Urls $urls
 * @var string $clientSlug
 * @var \MediasIndex\Storage\ProjectTotals $project
 * @var list<\MediasIndex\Storage\MediaRow> $medias
 * @var string|null $currentSlug
 */

use MediasIndex\View\Format;

$currentSlug ??= null;
?>
<?= $this->render('partials/sidebar-crumbs', [
    'trail' => [
        ['label' => $clientSlug, 'href' => $urls->client($clientSlug)],
        ['label' => $project->name, 'href' => null],
    ],
]) ?>
<ul class="item-list">
    <?php foreach ($medias as $media) { ?>
        <li>
            <a class="item<?= $media->slug === $currentSlug ? ' is-active' : '' ?>"
               href="<?= $this->e($urls->media($clientSlug, $project->slug, $media->slug)) ?>">
                <span class="item-name"><?= $this->e($media->name) ?></span>
                <span class="item-meta">
                    <?= $this->e(Format::bytes($media->sizeBytes)) ?>
                    · <?= $this->e(Format::typeLabel($media->type)) ?>
                </span>
            </a>
        </li>
    <?php } ?>
</ul>
