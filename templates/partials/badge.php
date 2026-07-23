<?php

/**
 * @var \MediasIndex\View\View $this
 * @var string $type
 */

use MediasIndex\View\Format;

?>
<?php // Modifier from the raw identifier, label from the translation. ?>
<span class="badge badge-<?= $this->e(Format::typeModifier($type)) ?>"><?= $this->e(Format::typeLabel($type)) ?></span>
