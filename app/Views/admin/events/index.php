<div class="admin-head">
    <h1>Eventos</h1>
    <a class="btn btn-primary btn-sm" href="/admin/eventos/novo">+ Novo evento</a>
</div>
<form class="filters" method="get" action="/admin/eventos">
    <select name="status">
        <option value="">Todos</option>
        <option value="pending"<?= selected('pending', $status) ?>>Pendentes (sugestões)</option>
        <option value="approved"<?= selected('approved', $status) ?>>Publicados</option>
    </select>
    <button class="btn" type="submit">Filtrar</button>
</form>
<div class="table-wrap">
<table class="table">
    <thead><tr><th>Data</th><th>Evento</th><th>Cidade</th><th>Tipo</th><th>Situação</th><th>Vão</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($events as $ev): ?>
        <tr>
            <td class="nowrap"><?= e(date('d/m/Y H:i', strtotime($ev['start_at']))) ?></td>
            <td><a href="/admin/eventos/<?= (int) $ev['id'] ?>/editar"><?= e($ev['title']) ?></a><?php if ($ev['author']): ?><br><small class="muted">por <?= e($ev['author']) ?></small><?php endif; ?></td>
            <td><?= e($ev['city']) ?></td>
            <td><?= $ev['is_own'] ? '🦆 Ducks' : e($ev['organizer'] ?: 'Outra turma') ?></td>
            <td><span class="tag tag-status-<?= e($ev['status']) ?>"><?= $ev['status'] === 'pending' ? 'Pendente' : 'Publicado' ?></span></td>
            <td><?= (int) $ev['going'] ?></td>
            <td class="right nowrap">
                <?php if ($ev['status'] === 'pending'): ?>
                    <form method="post" action="/admin/eventos/<?= (int) $ev['id'] ?>/aprovar" class="inline-form"><?= $csrf->field() ?><button class="btn btn-sm btn-primary" type="submit">Aprovar</button></form>
                <?php else: ?>
                    <a class="btn btn-sm btn-ghost" href="/agenda/<?= (int) $ev['id'] ?>" target="_blank">Ver</a>
                <?php endif; ?>
                <form method="post" action="/admin/eventos/<?= (int) $ev['id'] ?>/excluir" class="inline-form" data-confirm="Excluir este evento?"><?= $csrf->field() ?><button class="btn btn-sm btn-danger" type="submit">Excluir</button></form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
