<?php

/**
 * @var \MediasIndex\View\View $this
 * @var \MediasIndex\View\Urls $urls
 * @var list<\MediasIndex\Storage\ClientTotals> $clients
 * @var string|null $current slug of the client being viewed, if any
 */

use MediasIndex\View\Format;

$current ??= null;
?>
<?= $this->render('partials/sidebar-crumbs') ?>
<ul class="item-list">
    <?php foreach ($clients as $client) { ?>
        <li>
            <a class="item<?= $client->slug === $current ? ' is-active' : '' ?>"
               href="<?= $this->e($urls->client($client->slug)) ?>">
                <span class="item-name"><?= $this->e($client->name) ?></span>
                <span class="item-meta">
                    <?= $this->e(Format::plural($client->projectCount, 'projet', 'projets', 'pas de projet')) ?>
                    · <?= $this->e(Format::bytes($client->sizeBytes)) ?>
                </span>
            </a>
        </li>
    <?php } ?>
</ul>
