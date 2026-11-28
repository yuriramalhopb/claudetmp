<p class="muted">Intenções de velas já acesas, marcadas para serem lembradas na Santa Missa. O texto só é visto pela equipe.</p>
<form class="toolbar" method="get" action="/admin/missas">
    <label>De <input type="date" name="de" value="<?= e($from) ?>"></label>
    <label>Até <input type="date" name="ate" value="<?= e($to) ?>"></label>
    <label class="check"><input type="checkbox" name="todas" value="1"<?= checked(!$pending) ?>> incluir as já rezadas</label>
    <button class="btn btn-gold btn-sm" type="submit">Filtrar</button>
    <a class="btn btn-ghost-dark btn-sm" target="_blank" href="/admin/missas/imprimir?de=<?= e($from) ?>&amp;ate=<?= e($to) ?><?= $pending ? '' : '&amp;todas=1' ?>">🖨️ Folha para imprimir</a>
</form>
<?php if ($items): ?>
<form class="inline-form" method="post" action="/admin/missas/marcar" data-confirm="Marcar todas as intenções deste período como rezadas?">
    <?= $csrf->field() ?><input type="hidden" name="de" value="<?= e($from) ?>"><input type="hidden" name="ate" value="<?= e($to) ?>">
    <button class="btn btn-ghost-dark btn-sm" type="submit">✔ Marcar todas como rezadas</button>
</form>
<?php endif; ?>
<div class="panel">
    <?php foreach ($items as $c): ?>
        <div class="mass-item<?= $c['mass_done_at'] ? ' done' : '' ?>">
            <strong><?= e($c['name']) ?></strong>
            <?= $c['for_whom'] ? ' · por <strong>' . e($c['for_whom']) . '</strong>' : '' ?>
            <small class="muted"> · <?= e(App\Models\Candle::CATEGORIES[$c['category']][0] ?? '') ?> · <?= e(datetime_short($c['lit_at'])) ?></small>
            <?php if (trim((string) $c['intention']) !== ''): ?><blockquote><?= nl2br_e($c['intention']) ?></blockquote><?php else: ?><p class="muted">(sem texto)</p><?php endif; ?>
            <form class="inline-form" method="post" action="/admin/velas/<?= (int) $c['id'] ?>/missa">
                <?= $csrf->field() ?>
                <button class="btn btn-ghost-dark btn-sm" type="submit"><?= $c['mass_done_at'] ? 'Desmarcar (rezada em ' . e(date('d/m', strtotime($c['mass_done_at']))) . ')' : '🕊️ Marcar como rezada' ?></button>
            </form>
        </div>
    <?php endforeach; ?>
    <?php if (!$items): ?><p class="muted center">Nenhuma intenção neste período. 🙏</p><?php endif; ?>
</div>
