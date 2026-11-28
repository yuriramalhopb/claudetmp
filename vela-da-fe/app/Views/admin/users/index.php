<p><a class="btn btn-gold btn-sm" href="/admin/equipe/novo">+ Adicionar pessoa</a></p>
<div class="panel table-wrap">
<table class="table">
    <thead><tr><th>Nome</th><th>E-mail</th><th>Perfil</th><th>Acesso</th><th>Último login</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($users as $u): ?>
        <tr>
            <td><?= e($u['name']) ?></td><td><?= e($u['email']) ?></td>
            <td><span class="badge <?= $u['role'] === 'admin' ? 'badge-info' : '' ?>"><?= $u['role'] === 'admin' ? 'Administrador' : 'Moderador' ?></span></td>
            <td><span class="badge <?= $u['status'] === 'active' ? 'badge-ok' : 'badge-bad' ?>"><?= e(App\Models\User::STATUS[$u['status']] ?? $u['status']) ?></span></td>
            <td><?= e(datetime_short($u['last_login_at'])) ?></td>
            <td><a class="btn btn-ghost-dark btn-sm" href="/admin/equipe/<?= (int) $u['id'] ?>/editar">Editar</a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<p class="hint"><strong>Administrador</strong>: acesso total. <strong>Moderador</strong>: velas, pagamentos, moderação, missas, graças, orações, mensagens e horários; não vê configurações, financeiro nem equipe.</p>
