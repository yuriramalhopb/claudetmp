<?php $pending = $c['status'] === 'pending_payment'; $lit = $c['status'] === 'active' && strtotime((string) $c['expires_at']) > time(); ?>
<p><a href="/admin/velas">← Todas as velas</a> · <?= $view->partial('candle-status', ['c' => $c]) ?></p>

<?php if ($pending): ?>
<div class="panel pix-warn">
    <h2>Conferir o Pix de <?= e(money((int) $c['amount'])) ?></h2>
    <p>Procure no extrato do banco um Pix de <strong><?= e(money((int) $c['amount'])) ?></strong>
        com o identificador <code>VELA<?= e($c['code']) ?></code> (aparece no comprovante de muitos bancos) ou com o nome <strong><?= e($c['name']) ?></strong>.
        <?php if ($c['paid_claimed_at']): ?><br>✔ A pessoa avisou que pagou em <?= e(datetime_short($c['paid_claimed_at'])) ?>.<?php endif; ?></p>
    <div class="action-row">
        <form class="inline-form" method="post" action="/admin/velas/<?= (int) $c['id'] ?>/confirmar" data-confirm="Confirmar o recebimento do Pix e acender a vela?"><?= $csrf->field() ?><button class="btn btn-gold" type="submit">✔ Confirmar pagamento e acender</button></form>
        <form class="inline-form" method="post" action="/admin/velas/<?= (int) $c['id'] ?>/cancelar" data-confirm="Cancelar este pedido?"><?= $csrf->field() ?><button class="btn btn-ghost-dark" type="submit">Cancelar pedido</button></form>
    </div>
</div>
<?php endif; ?>

<div class="grid-2">
    <div class="panel">
        <h2>Dados da vela</h2>
        <dl class="kv">
            <dt>Código</dt><dd><code><?= e($c['code']) ?></code> <?php if ($c['status'] === 'active'): ?><a href="/vela/<?= e($c['code']) ?>" target="_blank" rel="noopener">ver no site ↗</a><?php endif; ?></dd>
            <dt>Tipo</dt><dd><span class="dot ct-<?= (int) $c['type_id'] ?>"></span> <?= e($c['type_name']) ?></dd>
            <dt>Duração</dt><dd><?= e(hours_human((int) $c['hours'])) ?></dd>
            <dt>Valor</dt><dd><?= e(money((int) $c['amount'], true)) ?><?= $c['paid_at'] ? ' · pago em ' . e(datetime_short($c['paid_at'])) : '' ?></dd>
            <dt>Criada em</dt><dd><?= e(datetime_short($c['created_at'])) ?></dd>
            <dt>Acesa em</dt><dd><?= e(datetime_short($c['lit_at'])) ?></dd>
            <dt>Acesa até</dt><dd><?= e(datetime_short($c['expires_at'])) ?><?= $lit ? ' (restam ' . e(remaining_human($c['expires_at'])) . ')' : '' ?></dd>
            <dt>Orações</dt><dd>🙏 <?= (int) $c['prayer_count'] ?></dd>
            <dt>E-mail</dt><dd><?= $c['email'] ? e($c['email']) . ($c['notify_email'] ? ' (quer avisos)' : '') : '—' ?></dd>
            <dt>Motivo</dt><dd><?= e($p['category_emoji'] . ' ' . $p['category_label']) ?></dd>
        </dl>
    </div>
    <div class="panel">
        <h2>O que a pessoa escreveu</h2>
        <dl class="kv">
            <dt>Nome</dt><dd><?= e($c['name']) ?> <small class="muted">(<?= $c['show_name'] ? 'mostra no mural' : 'anônimo no mural' ?>)</small></dd>
            <dt>Por quem</dt><dd><?= $c['for_whom'] ? e($c['for_whom']) : '—' ?></dd>
            <dt>Intenção</dt><dd><?= $c['intention'] ? nl2br_e($c['intention']) : '—' ?> <small class="muted">(<?= $c['show_intention'] ? 'mostra no mural' : 'privada' ?>)</small></dd>
            <dt>Missa</dt><dd><?= $c['mass_intention'] ? '🕊️ sim' . ($c['mass_done_at'] ? ' — rezada em ' . e(datetime_short($c['mass_done_at'])) : ' — ainda não rezada') : 'não' ?></dd>
        </dl>
        <?php if ($c['mod_status'] === 'pending'): ?>
            <p class="notice">Este texto está retido para moderação (não aparece no mural).</p>
        <?php endif; ?>
        <div class="action-row">
            <?php if ($c['mod_status'] !== 'approved'): ?>
                <form class="inline-form" method="post" action="/admin/velas/<?= (int) $c['id'] ?>/aprovar"><?= $csrf->field() ?><button class="btn btn-gold btn-sm" type="submit">✔ Aprovar texto</button></form>
            <?php endif; ?>
            <?php if ($c['mod_status'] !== 'rejected'): ?>
                <form class="inline-form" method="post" action="/admin/velas/<?= (int) $c['id'] ?>/recusar" data-confirm="Recusar o texto? A vela ficará anônima e sem o texto."><?= $csrf->field() ?><button class="btn btn-ghost-dark btn-sm" type="submit">✖ Recusar texto</button></form>
            <?php endif; ?>
            <?php if ($c['mass_intention'] && $c['status'] === 'active'): ?>
                <form class="inline-form" method="post" action="/admin/velas/<?= (int) $c['id'] ?>/missa"><?= $csrf->field() ?><button class="btn btn-ghost-dark btn-sm" type="submit"><?= $c['mass_done_at'] ? 'Desmarcar como rezada' : '🕊️ Marcar como rezada na missa' ?></button></form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($c['status'] === 'active'): ?>
<div class="panel">
    <h2>Controle da vela</h2>
    <div class="action-row">
        <form class="inline-form toolbar" method="post" action="/admin/velas/<?= (int) $c['id'] ?>/prolongar">
            <?= $csrf->field() ?>
            <label>Prolongar (horas) <input type="number" name="hours" min="1" max="8760" value="24"></label>
            <button class="btn btn-ghost-dark btn-sm" type="submit">Prolongar</button>
        </form>
        <?php if ($lit): ?>
        <form class="inline-form" method="post" action="/admin/velas/<?= (int) $c['id'] ?>/apagar" data-confirm="Apagar esta vela agora?"><?= $csrf->field() ?><button class="btn btn-ghost-dark btn-sm" type="submit">Apagar agora</button></form>
        <?php endif; ?>
        <form class="inline-form" method="post" action="/admin/velas/<?= (int) $c['id'] ?>/remover" data-confirm="Remover esta vela do site?"><?= $csrf->field() ?><button class="btn btn-danger btn-sm" type="submit">Remover do site</button></form>
    </div>
</div>
<?php elseif (in_array($c['status'], ['removed', 'cancelled'], true)): ?>
<div class="panel"><form class="inline-form" method="post" action="/admin/velas/<?= (int) $c['id'] ?>/restaurar"><?= $csrf->field() ?><button class="btn btn-ghost-dark btn-sm" type="submit">Restaurar vela</button></form></div>
<?php endif; ?>

<div class="grid-2">
    <div class="panel">
        <h2>Observação interna</h2>
        <form method="post" action="/admin/velas/<?= (int) $c['id'] ?>/nota">
            <?= $csrf->field() ?>
            <div class="field"><input name="admin_note" maxlength="255" value="<?= e($c['admin_note']) ?>" placeholder="Visível só para a equipe"></div>
            <button class="btn btn-ghost-dark btn-sm" type="submit">Salvar</button>
        </form>
    </div>
    <?php if ($auth->isAdmin()): ?>
    <div class="panel">
        <h2>Excluir definitivamente</h2>
        <p class="muted">Use para atender a pedido de exclusão de dados (LGPD). Não dá para desfazer.</p>
        <form method="post" action="/admin/velas/<?= (int) $c['id'] ?>/excluir" data-confirm="Excluir esta vela PARA SEMPRE?"><?= $csrf->field() ?><button class="btn btn-danger btn-sm" type="submit">Excluir vela</button></form>
    </div>
    <?php endif; ?>
</div>
