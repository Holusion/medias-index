<?php

/**
 * Says what went wrong and nothing more: the details of a 500 belong in the
 * server log, not in the browser.
 *
 * @var \MediasIndex\View\View $this
 * @var \MediasIndex\View\Urls $urls
 * @var int $status
 * @var string $heading
 * @var string $message
 */
?>
<h1><?= $this->e($heading) ?></h1>

<div class="error-page">
    <span class="error-code"><?= (int) $status ?></span>
    <p class="error-message"><?= $this->e($message) ?></p>
</div>

<div class="toolbar">
    <a class="btn btn-outline" href="<?= $this->e($urls->home()) ?>">Retour à l'accueil</a>
</div>
