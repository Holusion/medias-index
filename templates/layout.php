<?php

/**
 * The page frame. Receives already-rendered strings for the two regions rather
 * than callbacks, so a controller composes its page and this only arranges it.
 *
 * @var \MediasIndex\View\View $this
 * @var string $title
 * @var array<string, string|null> $breadcrumb label => href, null for the current page
 * @var string $sidebar rendered html
 * @var string $content rendered html
 * @var string $bodyModifiers extra classes on .app-body
 */

$bodyModifiers ??= '';
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $this->e($title) ?> — medias-index</title>
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body>
<div class="page">
    <header class="page-header">
        <a class="brand" href="<?= $this->e($urls->home()) ?>">medias-index</a>
        <nav class="nav">
            <?php foreach ($breadcrumb as $label => $href) { ?>
                <span class="separator">/</span>
                <?php if ($href === null) { ?>
                    <span><?= $this->e((string) $label) ?></span>
                <?php } else { ?>
                    <a href="<?= $this->e($href) ?>"><?= $this->e((string) $label) ?></a>
                <?php } ?>
            <?php } ?>
        </nav>
    </header>

    <?php // Error pages have no sidebar; an empty 300px column reads as a bug. ?>
    <div class="app-body <?= $this->e($bodyModifiers) ?><?= $sidebar === '' ? ' has-no-sidebar' : '' ?>">
        <?php if ($sidebar !== '') { ?>
            <aside class="sidebar"><?= $sidebar ?></aside>
        <?php } ?>
        <main class="page-main"><?= $content ?></main>
    </div>

    <footer class="page-footer">
        Interface d'administration
    </footer>
</div>
</body>
</html>
