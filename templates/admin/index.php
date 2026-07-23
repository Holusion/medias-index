<?php

/**
 * @var \MediasIndex\View\View $this
 * @var \MediasIndex\View\Urls $urls
 * @var list<\MediasIndex\Storage\ClientTotals> $clients
 * @var int $totalSizeBytes
 * @var int $projectCount
 * @var int $mediaCount
 * @var array<string, mixed>|null $lastScan row from the scans table
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
        'Clients' => Format::number(count($clients)),
        'Projets' => Format::number($projectCount),
        'Médias' => Format::number($mediaCount),
    ],
]) ?>

<h2>Indexation</h2>

<?php
$stats = is_string($lastScan['stats'] ?? null) ? json_decode($lastScan['stats'], true) : null;
$failed = ($lastScan['status'] ?? null) === 'failed';
?>

<?php if ($lastScan === null) { ?>
    <p class="muted">Aucun scan enregistré.</p>
<?php } else { ?>
    <p class="muted">
        Dernier scan
        <?= $this->e(Format::dateTime((int) $lastScan['started_at'])) ?>
        (<?= $this->e((string) $lastScan['triggered_by']) ?>) —
        <?php if ($failed) { ?>
            <strong>échec</strong> : <?= $this->e((string) ($lastScan['error'] ?? '')) ?>
        <?php } elseif (is_array($stats)) { ?>
            <?= $this->e(Format::plural((int) ($stats['medias'] ?? 0), 'média', 'médias', 'aucun média')) ?>,
            <?= $this->e(Format::plural((int) ($stats['deleted_medias'] ?? 0), 'retiré', 'retirés', 'aucun retrait')) ?>,
            <?= (int) ($stats['duration_seconds'] ?? 0) ?> s
        <?php } ?>
    </p>
<?php } ?>

<div class="toolbar">
    <?php // POST, because it changes something; the controller redirects back
          // so a refresh cannot scan again. ?>
    <form method="post" action="/scan" data-scan-form>
        <button type="submit" class="btn btn-primary">
            <span class="btn-label">Lancer l'indexation</span>
        </button>
    </form>
    <a class="btn btn-outline" href="/doctor">Vérification de l'environnement</a>
    <a class="btn btn-outline" href="/styleguide">Références de style</a>
</div>
