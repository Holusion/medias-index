<?php

/**
 * @var \MediasIndex\View\View $this
 * @var array<string, string> $figures label => already-formatted value
 */
?>
<div class="stats">
    <?php foreach ($figures as $label => $value) { ?>
        <div class="stat">
            <span class="stat-label"><?= $this->e((string) $label) ?></span>
            <span class="stat-value"><?= $this->e($value) ?></span>
        </div>
    <?php } ?>
</div>
