<?php

/**
 * @var \MediasIndex\View\View $this
 * @var \MediasIndex\View\Urls $urls
 * @var list<\MediasIndex\Storage\ClientTotals> $clients
 * @var int $totalSizeBytes
 * @var int $projectCount
 * @var int $mediaCount
 */

use MediasIndex\View\Format;

?>
<h1>Vue d'ensemble</h1>

<?php if ($clients === []) { ?>
    <div class="empty-state">
        Aucun contenu indexé. Lancez <code>php bin/scan.php</code> pour indexer l'arborescence.
    </div>
<?php } ?>

<?= $this->render('partials/stats', [
    'figures' => [
        'Contenu indexé' => Format::bytes($totalSizeBytes),
        'Clients' => (string) count($clients),
        'Projets' => (string) $projectCount,
        'Medias' => (string) $mediaCount,
    ],
]) ?>

<div class="toolbar">
    <a class="btn btn-outline" href="/doctor">Vérification de l'environnement</a>
    <a class="btn btn-outline" href="/styleguide">Références de style</a>
</div>
