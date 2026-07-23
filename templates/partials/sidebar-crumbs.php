<?php

/**
 * The sidebar header: where in the hierarchy the list below sits.
 *
 * Home is always the root and always a link; anything after it names the level
 * whose children are being listed, and the last crumb is the current one, so it
 * is text rather than a link to the page you are already on.
 *
 * @var \MediasIndex\View\View $this
 * @var \MediasIndex\View\Urls $urls
 * @var list<array{label: string, href: string|null}> $trail levels below home
 */

$trail ??= [];
$lastIndex = count($trail) - 1;
?>
<nav class="sidebar-crumbs" aria-label="Fil d'Ariane">
    <a class="crumb crumb-home" href="<?= $this->e($urls->home()) ?>" aria-label="Accueil"
       <?= $trail === [] ? 'aria-current="page"' : '' ?>><?= $this->render('partials/icon-home') ?></a>

    <?php foreach ($trail as $index => $crumb) { ?>
        <span class="crumb-separator" aria-hidden="true">›</span>
        <?php if ($index === $lastIndex || ($crumb['href'] ?? null) === null) { ?>
            <span class="crumb crumb-current" aria-current="page"><?= $this->e($crumb['label']) ?></span>
        <?php } else { ?>
            <a class="crumb" href="<?= $this->e($crumb['href']) ?>"><?= $this->e($crumb['label']) ?></a>
        <?php } ?>
    <?php } ?>
</nav>
