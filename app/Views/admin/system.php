<h1>Sistema</h1>
<div class="stat-grid">
    <div class="stat<?= $cronOk ? '' : ' stat-alert' ?>"><strong><?= $cronOk ? '✔' : '!' ?></strong><span>Agendador (cron)<br><?= $cronLast ? 'última execução ' . e(date('d/m H:i', strtotime($cronLast))) : 'nunca executou' ?></span></div>
    <div class="stat<?= $mailStats['failed'] ? ' stat-alert' : '' ?>"><strong><?= (int) $mailStats['sent_24h'] ?></strong><span>E-mails enviados (24h)<br><?= (int) $mailStats['pending'] ?> na fila · <?= (int) $mailStats['failed'] ?> com falha</span></div>
    <div class="stat<?= $pushReady ? '' : ' stat-alert' ?>"><strong><?= (int) $pushDevices ?></strong><span>Aparelhos com notificação<br><?= $pushReady ? 'push configurado' : 'push NÃO configurado' ?></span></div>
    <div class="stat<?= $backupLast && time() - strtotime($backupLast) < 30 * 3600 ? '' : ' stat-alert' ?>"><strong><?= count($backups) ?></strong><span>Arquivos de backup<br>último: <?= $backupLast ? e(date('d/m H:i', strtotime($backupLast))) : 'nenhum' ?></span></div>
</div>

<?php if (!$cronOk): ?>
<div class="notice notice-warning">
    ⚠️ O agendador não está rodando. Sem ele, avisos, lembretes, notificações e backups automáticos não acontecem.
    Configure no painel da hospedagem (Cron Jobs), a cada 5 minutos:<br>
    <code>php <?= e($basePath) ?>/bin/cron.php</code>
</div>
<?php endif; ?>

<div class="two-col">
    <section class="card">
        <h2>✉️ E-mail</h2>
        <p>Modo atual: <strong><?= e(['smtp' => 'SMTP', 'mail' => 'função mail() do PHP', 'log' => 'somente registro (não envia)'][$mailDriver] ?? $mailDriver) ?></strong></p>
        <?php if ($mailDriver === 'log'): ?><p class="notice notice-warning">Os e-mails NÃO estão sendo enviados. Configure <code>MAIL_DRIVER=smtp</code> e os dados <code>SMTP_*</code> no <code>.env</code>.</p><?php endif; ?>
        <?php if ($mailStats['last_error']): ?><p class="notice notice-error">Último erro: <?= e($mailStats['last_error']) ?></p><?php endif; ?>
        <form method="post" action="/admin/sistema/email-teste"><?= $csrf->field() ?><button class="btn" type="submit">Enviar e-mail de teste para mim</button></form>
    </section>
    <section class="card">
        <h2>🔔 Notificações no celular</h2>
        <p>Seus aparelhos com notificação ativa: <strong><?= (int) $myDevices ?></strong></p>
        <p class="muted">Para ativar: abra o site/app no celular, entre na sua conta e toque em <strong>🔔 Ativar avisos</strong> no menu. No iPhone, o app precisa estar instalado na Tela de Início (iOS 16.4+).</p>
        <form method="post" action="/admin/push/teste"><?= $csrf->field() ?><button class="btn" type="submit"<?= $pushReady ? '' : ' disabled' ?>>Enviar notificação de teste para mim</button></form>
    </section>
</div>

<section class="card">
    <div class="admin-head">
        <h2>💾 Backups</h2>
        <form method="post" action="/admin/sistema/backup" data-confirm="Criar um backup agora? Pode levar alguns segundos."><?= $csrf->field() ?><button class="btn btn-primary btn-sm" type="submit">Fazer backup agora</button></form>
    </div>
    <p class="muted">Backup automático diário às <?= (int) $backupHour ?>h (banco + fotos), mantidos por 30 dias em <code>storage/backups</code> (fora da área pública). Copie-os periodicamente para outro lugar (Google Drive, HD externo). Para restaurar: <code>php bin/restore.php</code>.</p>
    <?php if ($backups): ?>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Data</th><th>Arquivo</th><th>Tamanho</th></tr></thead>
        <tbody>
        <?php foreach (array_slice($backups, 0, 30) as $b): ?>
            <tr><td class="nowrap"><?= e(date('d/m/Y H:i', $b['time'])) ?></td><td><code><?= e($b['name']) ?></code></td><td><?= e(number_format($b['size'] / 1048576, 1, ',', '.')) ?> MB</td></tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php else: ?><p class="empty">Nenhum backup ainda.</p><?php endif; ?>
</section>
