<?php if (!empty($flashes)): ?>
<div class="container flashes" role="status">
    <?php foreach ($flashes as $f): ?>
        <div class="flash flash-<?= e(in_array($f['type'], ['success', 'error', 'warning', 'info'], true) ? $f['type'] : 'info') ?>">
            <span><?= e($f['message']) ?></span>
            <button type="button" class="flash-close" data-dismiss aria-label="Fechar">×</button>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
