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
<p class="sidebar-title">Clients</p>
<ul class="item-list">
    <?php foreach ($clients as $client) { ?>
        <li>
            <a class="item<?= $client->slug === $current ? ' is-active' : '' ?>"
               href="<?= $this->e($urls->client($client->slug)) ?>">
                <span class="item-name"><?= $this->e($client->name) ?></span>
                <span class="item-meta">
                    <?= $client->projectCount ?> projet(s) · <?= $this->e(Format::bytes($client->sizeBytes)) ?>
                </span>
            </a>
        </li>
    <?php } ?>
</ul>
