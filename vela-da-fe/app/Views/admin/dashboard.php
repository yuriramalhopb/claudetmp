<?php
$max = max(1, ...array_column($perDay, 'n'));
?>
<?php if (!$pixOk): ?>
    <p class="notice">💡 A chave Pix ainda não foi configurada: só velas gratuitas estão disponíveis. <a href="/admin/configuracoes">Configurar agora</a>.</p>
<?php endif; ?>
<?php if ($mailDriver === 'log'): ?>
    <p class="notice">✉️ O envio de e-mails está em modo de teste (grava em arquivo). Veja como ativar em <a href="/admin/sistema">Sistema</a>.</p>
<?php endif; ?>

<div class="stat-cards">
    <div class="stat"><strong><?= (int) $s['lit_now'] ?></strong><span>velas acesas agora</span></div>
    <div class="stat"><strong><?= (int) $s['lit_today'] ?></strong><span>acesas hoje</span></div>
    <div class="stat"><strong><?= (int) $s['lit_month'] ?></strong><span>acesas neste mês</span></div>
    <div class="stat"><strong><?= (int) $s['lit_total'] ?></strong><span>acesas no total</span></div>
    <div class="stat"><strong><?= (int) $s['prayers'] ?></strong><span>orações pelas velas</span></div>
    <?php if ($auth->isAdmin()): ?>
    <div class="stat"><strong><?= e(money((int) $s['revenue_month'])) ?></strong><span>arrecadado no mês (Pix)</span></div>
    <div class="stat"><strong><?= e(money((int) $s['revenue_total'])) ?></strong><span>arrecadado no total</span></div>
    <?php endif; ?>
</div>

<div class="stat-cards">
    <a class="stat<?= $s['pending_payment'] ? ' alert' : '' ?>" href="/admin/velas?status=pending_payment"><strong><?= (int) $s['pending_payment'] ?></strong><span>aguardando conferência do Pix</span></a>
    <a class="stat<?= $s['pending_moderation'] ? ' alert' : '' ?>" href="/admin/velas?status=moderation"><strong><?= (int) $s['pending_moderation'] ?></strong><span>textos para moderar</span></a>
    <a class="stat<?= $s['mass_pending'] ? ' alert' : '' ?>" href="/admin/missas"><strong><?= (int) $s['mass_pending'] ?></strong><span>intenções para a missa</span></a>
    <a class="stat<?= $s['pending_testimonials'] ? ' alert' : '' ?>" href="/admin/gracas"><strong><?= (int) $s['pending_testimonials'] ?></strong><span>testemunhos para ler</span></a>
</div>

<div class="panel">
    <h2>Velas acesas nos últimos 14 dias</h2>
    <svg class="bars bar-chart" viewBox="0 0 280 80" role="img" aria-label="Gráfico de velas acesas por dia">
        <?php foreach ($perDay as $i => $d): $h = round($d['n'] / $max * 42, 2); ?>
            <rect x="<?= $i * 20 + 3 ?>" y="<?= round(60 - $h, 2) ?>" width="14" height="<?= $h ?>" rx="2"><title><?= e(date('d/m', strtotime($d['day']))) ?>: <?= (int) $d['n'] ?></title></rect>
            <?php if ($d['n'] > 0): ?><text class="val" x="<?= $i * 20 + 10 ?>" y="<?= round(57 - $h, 2) ?>"><?= (int) $d['n'] ?></text><?php endif; ?>
            <text x="<?= $i * 20 + 10 ?>" y="74"><?= e(date('d', strtotime($d['day']))) ?></text>
        <?php endforeach; ?>
    </svg>
    <div class="muted center"><?= e(date('d/m', strtotime($perDay[0]['day']))) ?> → <?= e(date('d/m', strtotime(end($perDay)['day']))) ?> · pico de <?= (int) $max ?> por dia</div>
</div>

<div class="grid-2">
    <div class="panel">
        <h2>Aguardando pagamento</h2>
        <?php if ($awaiting): ?>
            <div class="table-wrap"><table class="table">
                <?php foreach ($awaiting as $c): ?>
                    <tr><td><a href="/admin/velas/<?= (int) $c['id'] ?>"><?= e($c['name']) ?></a><br><small class="muted"><?= e(datetime_short($c['created_at'])) ?></small></td>
                        <td class="num"><?= e(money((int) $c['amount'])) ?></td><td><?= $view->partial('candle-status', ['c' => $c]) ?></td></tr>
                <?php endforeach; ?>
            </table></div>
            <p><a href="/admin/velas?status=pending_payment">Ver todos →</a></p>
        <?php else: ?><p class="muted">Nenhum pagamento pendente. 🎉</p><?php endif; ?>
    </div>
    <div class="panel">
        <h2>Textos para moderar</h2>
        <?php if ($moderation): ?>
            <div class="table-wrap"><table class="table">
                <?php foreach ($moderation as $c): ?>
                    <tr><td><a href="/admin/velas/<?= (int) $c['id'] ?>"><?= e($c['name']) ?></a><br><small class="muted"><?= e(excerpt($c['intention'] ?? '', 70)) ?></small></td>
                        <td><?= $view->partial('candle-status', ['c' => $c]) ?></td></tr>
                <?php endforeach; ?>
            </table></div>
            <p><a href="/admin/velas?status=moderation">Ver todos →</a></p>
        <?php else: ?><p class="muted">Nada para moderar. 🙏</p><?php endif; ?>
    </div>
</div>

<div class="grid-2">
    <div class="panel">
        <h2>Por motivo</h2>
        <?php $tot = max(1, array_sum(array_column($byCategory, 'n'))); ?>
        <ul class="bar-list">
            <?php foreach ($byCategory as $r): $cat = App\Models\Candle::CATEGORIES[$r['category']] ?? ['Outros', '']; ?>
                <li><span><?= e($cat[1] . ' ' . $cat[0]) ?></span><progress max="<?= $tot ?>" value="<?= (int) $r['n'] ?>"></progress><span><?= (int) $r['n'] ?></span></li>
            <?php endforeach; ?>
            <?php if (!$byCategory): ?><li class="muted">Sem dados ainda.</li><?php endif; ?>
        </ul>
    </div>
    <div class="panel">
        <h2>Por tipo de vela</h2>
        <?php $tot2 = max(1, array_sum(array_column($byType, 'n'))); ?>
        <ul class="bar-list">
            <?php foreach ($byType as $r): ?>
                <li><span><?= e($r['name']) ?></span><progress max="<?= $tot2 ?>" value="<?= (int) $r['n'] ?>"></progress><span><?= (int) $r['n'] ?></span></li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
