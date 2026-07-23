<?php

/**
 * @var \MediasIndex\View\View $this
 */

use MediasIndex\Indexer\MediaType;

?>
<h1>Références de style</h1>
<p>Aperçu des composants disponibles pour construire l'interface.</p>

<h2>Boutons</h2>
<p class="toolbar">
    <button class="btn">Défaut</button>
    <button class="btn btn-primary">Primaire</button>
    <button class="btn btn-secondary">Secondaire</button>
    <button class="btn btn-outline btn-primary">Contour</button>
    <button class="btn btn-small">Petit</button>
    <button class="btn btn-danger">Danger</button>
    <button class="btn" disabled>Désactivé</button>
</p>

<h2>Types de media</h2>
<p class="toolbar">
    <?php foreach ([MediaType::UNKNOWN, MediaType::HTML, MediaType::KRPANO] as $type) { ?>
        <?= $this->render('partials/badge', ['type' => $type]) ?>
    <?php } ?>
    <?= $this->render('partials/badge', ['type' => 'type-inconnu']) ?>
</p>
<p class="muted">
    Le dernier montre le repli neutre : un type écrit par une version plus
    récente reste lisible.
</p>

<h2>Statistiques</h2>
<?= $this->render('partials/stats', [
    'figures' => ['Libellé' => '1 284', 'Avec unité' => '12,9 Mio'],
]) ?>

<h2>Texte</h2>
<h3>Titre de niveau 3</h3>
<p>Un paragraphe courant avec un <a href="#">lien</a> et du <code>code inline</code>.</p>
<p class="muted">Information secondaire : dates, tailles, compteurs.</p>
