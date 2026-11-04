<?php use App\Models\User; ?>
<div class="admin-head">
    <h1>Trilheiros <small class="muted">(<?= (int) $total ?>)</small></h1>
    <a class="btn btn-sm" href="/admin/trilheiros/exportar.csv">⬇ Exportar CSV</a>
</div>
<form class="filters" method="get" action="/admin/trilheiros">
    <input type="search" name="q" placeholder="Nome, e-mail, cidade…" value="<?= e($search) ?>">
    <select name="status">
        <option value="">Todas as situações</option>
        <?php foreach (User::STATUS as $k => $label): ?><option value="<?= e($k) ?>"<?= selected($k, $status) ?>><?= e($label) ?></option><?php endforeach; ?>
    </select>
    <button class="btn" type="submit">Filtrar</button>
</form>
<div class="table-wrap">
<table class="table">
    <thead><tr><th></th><th>Nome</th><th>Contato</th><th>Moto</th><th>Situação</th><th>Perfil</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($users as $u): ?>
        <tr>
            <td><img class="avatar-sm" src="<?= e(avatar_url($u)) ?>" alt=""></td>
            <td><a href="/admin/trilheiros/<?= (int) $u['id'] ?>"><?= e($u['name']) ?></a><?php if ($u['nickname']): ?><br><small class="muted"><?= e($u['nickname']) ?></small><?php endif; ?></td>
            <td><small><?= e($u['email']) ?><br><?= e($u['phone']) ?></small></td>
            <td><small><?= e(trim($u['bike_brand'] . ' ' . $u['bike_model'])) ?></small></td>
            <td><span class="tag tag-status-<?= e($u['status']) ?>"><?= e(User::STATUS[$u['status']] ?? $u['status']) ?></span></td>
            <td><?= e(User::ROLES[$u['role']] ?? $u['role']) ?></td>
            <td class="right nowrap">
                <?php if ((int) $u['id'] !== $auth->id()): ?>
                    <?php foreach (['approved' => 'Aprovar', 'blocked' => 'Bloquear'] as $st => $label): if ($u['status'] === $st) continue; ?>
                        <form method="post" action="/admin/trilheiros/<?= (int) $u['id'] ?>/status" class="inline-form">
                            <?= $csrf->field() ?><input type="hidden" name="status" value="<?= e($st) ?>">
                            <button class="btn btn-sm<?= $st === 'approved' ? ' btn-primary' : '' ?>" type="submit"><?= e($label) ?></button>
                        </form>
                    <?php endforeach; ?>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?= $view->partial('pagination', ['page' => $page, 'pages' => $pages, 'base' => '/admin/trilheiros', 'params' => ['q' => $search, 'status' => $status]]) ?>
