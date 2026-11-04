<h1>Auditoria</h1>
<p class="muted">Últimas 300 ações administrativas e eventos de segurança.</p>
<div class="table-wrap">
<table class="table">
    <thead><tr><th>Data</th><th>Usuário</th><th>Ação</th><th>Detalhes</th><th>IP</th></tr></thead>
    <tbody>
    <?php foreach ($logs as $l): ?>
        <tr>
            <td><?= e(date('d/m/Y H:i:s', strtotime($l['created_at']))) ?></td>
            <td><?= e($l['user_name'] ?? '—') ?></td>
            <td><code><?= e($l['action']) ?></code></td>
            <td><?= e($l['details']) ?></td>
            <td><small><?= e($l['ip']) ?></small></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
