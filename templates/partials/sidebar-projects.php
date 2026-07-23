<?php

/**
 * @var \MediasIndex\View\View $this
 * @var \MediasIndex\View\Urls $urls
 * @var string $clientSlug
 * @var list<\MediasIndex\Storage\ProjectTotals> $projects
 * @var \MediasIndex\Storage\ProjectTotals|null $selected
 */

use MediasIndex\View\Format;

?>
<p class="sidebar-title">Projets</p>
<ul class="item-list">
    <?php foreach ($projects as $project) { ?>
        <li>
            <a class="item<?= $selected?->id === $project->id ? ' is-active' : '' ?>"
               href="<?= $this->e($urls->project($clientSlug, $project->slug)) ?>">
                <span class="item-name"><?= $this->e($project->name) ?></span>
                <span class="item-meta">
                    <?= $project->mediaCount ?> media(s) · <?= $this->e(Format::bytes($project->sizeBytes)) ?>
                </span>
            </a>
        </li>
    <?php } ?>
</ul>
