<?php

/**
 * @var \MediasIndex\View\View $this
 * @var \MediasIndex\View\Urls $urls
 * @var string $clientSlug
 * @var list<\MediasIndex\Storage\ProjectTotals> $projects
 * @var string|null $currentSlug
 */

use MediasIndex\View\Format;

$currentSlug ??= null;
?>
<?= $this->render('partials/sidebar-crumbs', [
    'trail' => [['label' => $clientSlug, 'href' => null]],
]) ?>
<ul class="item-list">
    <?php foreach ($projects as $project) { ?>
        <li>
            <a class="item<?= $project->slug === $currentSlug ? ' is-active' : '' ?>"
               href="<?= $this->e($urls->project($clientSlug, $project->slug)) ?>">
                <span class="item-name"><?= $this->e($project->name) ?></span>
                <span class="item-meta">
                    <?= $this->e(Format::plural($project->mediaCount, 'média', 'médias', 'pas de média')) ?>
                    · <?= $this->e(Format::bytes($project->sizeBytes)) ?>
                </span>
            </a>
        </li>
    <?php } ?>
</ul>
