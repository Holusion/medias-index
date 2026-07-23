<?php

/**
 * @var \MediasIndex\View\View $this
 * @var \MediasIndex\View\Urls $urls
 * @var string $clientSlug
 * @var \MediasIndex\Storage\ClientTotals|null $clientTotals
 */

use MediasIndex\View\Format;

?>
<?= $this->render('partials/page-title', [
    'title' => $clientSlug,
    'back' => $urls->home(),
    'backLabel' => "Retour à la vue d'ensemble",
]) ?>

<div class="empty-state">Sélectionnez un projet.</div>

<?php if ($clientTotals !== null) {
    echo $this->render('partials/stats', [
        'figures' => [
            'Taille' => Format::bytes($clientTotals->sizeBytes),
            'Projets' => Format::number($clientTotals->projectCount),
            'Médias' => Format::number($clientTotals->mediaCount),
            'Créé le' => Format::day($clientTotals->ctime),
            'Modifié le' => Format::day($clientTotals->mtime),
        ],
    ]);
} ?>
