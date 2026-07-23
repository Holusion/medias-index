<?php

/**
 * @var \MediasIndex\View\View $this
 * @var string $type
 */

use MediasIndex\View\Format;

?>
<span class="badge badge-<?= $this->e(Format::typeModifier($type)) ?>"><?= $this->e($type) ?></span>
