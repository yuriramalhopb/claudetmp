<div class="status-tabs">
    <?php foreach (App\Models\Testimonial::STATUS as $k => $label): ?>
        <a class="<?= $status === $k ? 'is-active' : '' ?>" href="/admin/gracas?status=<?= e($k) ?>"><?= e($label) ?> (<?= (int) $counts[$k] ?>)</a>
    <?php endforeach; ?>
</div>
<div class="panel">
    <?php foreach ($items as $t): ?>
        <div class="mass-item">
            <strong><?= e($t['name']) ?></strong> <small class="muted">· <?= e(datetime_short($t['created_at'])) ?></small>
            <blockquote><?= nl2br_e($t['text']) ?></blockquote>
            <div class="action-row">
                <?php if ($t['status'] !== 'approved'): ?>
                <form class="inline-form" method="post" action="/admin/gracas/<?= (int) $t['id'] ?>/status"><?= $csrf->field() ?><input type="hidden" name="status" value="approved"><button class="btn btn-gold btn-sm" type="submit">✔ Publicar</button></form>
                <?php endif; ?>
                <?php if ($t['status'] !== 'rejected'): ?>
                <form class="inline-form" method="post" action="/admin/gracas/<?= (int) $t['id'] ?>/status"><?= $csrf->field() ?><input type="hidden" name="status" value="rejected"><button class="btn btn-ghost-dark btn-sm" type="submit">Recusar</button></form>
                <?php endif; ?>
                <form class="inline-form" method="post" action="/admin/gracas/<?= (int) $t['id'] ?>/excluir" data-confirm="Excluir este testemunho?"><?= $csrf->field() ?><button class="btn btn-ghost-dark btn-sm" type="submit">Excluir</button></form>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if (!$items): ?><p class="muted center">Nada por aqui.</p><?php endif; ?>
</div>
