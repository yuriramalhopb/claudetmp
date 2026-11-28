<form class="toolbar" method="get" action="/admin/financeiro">
    <label>De <input type="date" name="de" value="<?= e($from) ?>"></label>
    <label>Até <input type="date" name="ate" value="<?= e($to) ?>"></label>
    <button class="btn btn-gold btn-sm" type="submit">Ver</button>
    <a class="btn btn-ghost-dark btn-sm" href="/admin/financeiro/exportar?de=<?= e($from) ?>&amp;ate=<?= e($to) ?>">⬇ Exportar CSV</a>
</form>
<div class="stat-cards">
    <div class="stat"><strong><?= e(money((int) $total)) ?></strong><span>arrecadado no período</span></div>
    <div class="stat"><strong><?= (int) $count ?></strong><span>velas pagas</span></div>
    <div class="stat"><strong><?= $count ? e(money((int) round($total / $count))) : '—' ?></strong><span>valor médio</span></div>
</div>
<div class="panel table-wrap">
    <table class="table">
        <thead><tr><th>Data</th><th class="num">Velas pagas</th><th class="num">Total</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr><td><?= e(date('d/m/Y', strtotime($r['d']))) ?></td><td class="num"><?= (int) $r['n'] ?></td><td class="num"><?= e(money((int) $r['total'])) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="3" class="muted center">Nenhum pagamento confirmado neste período.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<p class="hint">Considera apenas pagamentos por Pix confirmados pela equipe. O dinheiro cai direto na conta da igreja; este relatório serve para conferência.</p>
