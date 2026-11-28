<div class="panel table-wrap">
<table class="table">
    <thead><tr><th>Quando</th><th>Quem</th><th>Ação</th><th>Detalhes</th><th>IP</th></tr></thead>
    <tbody>
    <?php foreach ($items as $a): ?>
        <tr><td><?= e(datetime_short($a['created_at'])) ?></td><td><?= e($a['user_name'] ?? '—') ?></td><td><code><?= e($a['action']) ?></code></td><td><?= e($a['details']) ?></td><td><?= e($a['ip']) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php require __DIR__ . '/../partials/pagination.php'; ?>
