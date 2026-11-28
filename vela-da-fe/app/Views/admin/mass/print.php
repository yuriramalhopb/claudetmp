<main class="print-sheet">
    <h1>Intenções para a Santa Missa</h1>
    <p class="center p-meta"><?= e($church['name']) ?> · <?= e(date('d/m/Y', strtotime($from))) ?> a <?= e(date('d/m/Y', strtotime($to))) ?> · <?= count($items) ?> intenção(ões)</p>
    <p class="no-print center"><button class="btn btn-gold" type="button" data-print>🖨️ Imprimir</button></p>
    <?php foreach ($items as $i => $c): ?>
        <div class="p-item">
            <strong><?= $i + 1 ?>. <?= e($c['name']) ?></strong><?= $c['for_whom'] ? ' — por ' . e($c['for_whom']) : '' ?>
            <span class="p-meta">(<?= e(App\Models\Candle::CATEGORIES[$c['category']][0] ?? '') ?>)</span>
            <?php if (trim((string) $c['intention']) !== ''): ?><div><?= nl2br_e($c['intention']) ?></div><?php endif; ?>
        </div>
    <?php endforeach; ?>
    <?php if (!$items): ?><p class="center">Nenhuma intenção neste período.</p><?php endif; ?>
</main>
