<div class="grid-2">
    <div class="panel">
        <h2>Situação</h2>
        <dl class="kv">
            <?php foreach ($info as $k => $v): ?><dt><?= e($k) ?></dt><dd><?= e($v) ?></dd><?php endforeach; ?>
            <dt>HTTPS</dt><dd><?= $httpsOk ? '✔ ativo' : '⚠ não está usando HTTPS (instale o certificado SSL antes de abrir o site ao público)' ?></dd>
            <dt>Fila de e-mails</dt><dd><?= (int) ($mail['pending'] ?? 0) ?> pendente(s) · <?= (int) ($mail['sent'] ?? 0) ?> enviado(s) · <?= (int) ($mail['failed'] ?? 0) ?> com falha</dd>
        </dl>
        <div class="action-row">
            <form class="inline-form" method="post" action="/admin/sistema/rotinas"><?= $csrf->field() ?><button class="btn btn-ghost-dark btn-sm" type="submit">▶ Rodar rotinas agora</button></form>
            <form class="inline-form" method="post" action="/admin/sistema/email-teste"><?= $csrf->field() ?><button class="btn btn-ghost-dark btn-sm" type="submit">✉ Enviar e-mail de teste para mim</button></form>
        </div>
        <p class="hint">As rotinas enviam e-mails, avisam sobre velas que vão se apagar, cancelam Pix vencidos e apagam e-mails antigos (LGPD). Configure o cron para rodar a cada 5 minutos: <code>php bin/cron.php</code>.</p>
    </div>
    <div class="panel">
        <h2>Backups</h2>
        <form class="inline-form" method="post" action="/admin/sistema/backup"><?= $csrf->field() ?><button class="btn btn-gold btn-sm" type="submit">Criar backup agora</button></form>
        <?php if ($backups): ?>
        <div class="table-wrap"><table class="table">
            <?php foreach (array_slice($backups, 0, 10) as $b): ?>
                <tr><td><?= e($b['name']) ?></td><td class="num"><?= e(round($b['size'] / 1024)) ?> KB</td><td><?= e(date('d/m/Y H:i', $b['time'])) ?></td>
                    <td><a href="/admin/sistema/backup/<?= e($b['name']) ?>">baixar</a></td></tr>
            <?php endforeach; ?>
        </table></div>
        <?php else: ?><p class="muted">Nenhum backup ainda. Baixe um e guarde fora do servidor.</p><?php endif; ?>
        <p class="hint">O backup contém dados pessoais (e-mails). Guarde com cuidado.</p>
    </div>
</div>
