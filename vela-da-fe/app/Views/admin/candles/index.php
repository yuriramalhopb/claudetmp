<?php
$tabs = ['' => 'Todas', 'lit' => 'Acesas', 'expired' => 'Apagadas', 'pending_payment' => 'Aguardando Pix', 'moderation' => 'Moderação', 'cancelled' => 'Canceladas', 'removed' => 'Removidas'];
?>
<div class="status-tabs">
    <?php foreach ($tabs as $k => $label): ?>
        <a class="<?= ($filters['status'] ?? '') === $k ? 'is-active' : '' ?>" href="/admin/velas<?= $k !== '' ? '?status=' . e($k) : '' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</div>
<form class="toolbar" method="get" action="/admin/velas">
    <input type="hidden" name="status" value="<?= e($filters['status']) ?>">
    <label>Buscar <input type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="nome, e-mail, código, texto…"></label>
    <label>Tipo <select name="tipo"><option value="">Todos</option><?php foreach ($types as $t): ?><option value="<?= (int) $t['id'] ?>"<?= selected($filters['type'], $t['id']) ?>><?= e($t['name']) ?></option><?php endforeach; ?></select></label>
    <label>Motivo <select name="categoria"><option value="">Todos</option><?php foreach (App\Models\Candle::CATEGORIES as $k => [$l, $em]): ?><option value="<?= e($k) ?>"<?= selected($filters['category'], $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
    <label class="check"><input type="checkbox" name="missa" value="1"<?= checked($filters['mass']) ?>> só p/ missa</label>
    <button class="btn btn-gold btn-sm" type="submit">Filtrar</button>
    <?php if ($auth->isAdmin()): ?><a class="btn btn-ghost-dark btn-sm" href="/admin/velas/exportar">⬇ Exportar CSV (mês)</a><?php endif; ?>
</form>

<p class="muted"><?= (int) $total ?> vela(s)</p>
<div class="panel table-wrap">
<table class="table">
    <thead><tr><th>#</th><th>Criada</th><th>Vela</th><th>Nome / por quem</th><th class="num">Valor</th><th>Situação</th><th>Acesa até</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($items as $c): ?>
        <tr>
            <td><a href="/admin/velas/<?= (int) $c['id'] ?>"><?= (int) $c['id'] ?></a></td>
            <td><?= e(datetime_short($c['created_at'])) ?></td>
            <td><span class="dot ct-<?= (int) $c['type_id'] ?>"></span> <?= e($c['type_name']) ?><br><small class="muted"><?= e(hours_human((int) $c['hours'])) ?></small></td>
            <td><strong><?= e($c['name']) ?></strong><?= $c['for_whom'] ? '<br><small>por ' . e($c['for_whom']) . '</small>' : '' ?></td>
            <td class="num"><?= e(money((int) $c['amount'], true)) ?></td>
            <td><?= $view->partial('candle-status', ['c' => $c]) ?></td>
            <td><?= $c['expires_at'] ? e(datetime_short($c['expires_at'])) : '—' ?></td>
            <td><a class="btn btn-ghost-dark btn-sm" href="/admin/velas/<?= (int) $c['id'] ?>">Abrir</a></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$items): ?><tr><td colspan="8" class="muted center">Nenhuma vela encontrada.</td></tr><?php endif; ?>
    </tbody>
</table>
</div>
<?php require __DIR__ . '/../../partials/pagination.php'; ?>
