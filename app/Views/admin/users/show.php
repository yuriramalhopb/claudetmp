<?php use App\Models\User; $u = $user; ?>
<p class="breadcrumb"><a href="/admin/trilheiros">← Trilheiros</a></p>
<div class="profile card">
    <img class="profile-photo" src="<?= e(avatar_url($u)) ?>" alt="">
    <div>
        <h1><?= e($u['name']) ?> <?php if ($u['nickname']): ?><small class="muted">"<?= e($u['nickname']) ?>"</small><?php endif; ?></h1>
        <p><span class="tag tag-status-<?= e($u['status']) ?>"><?= e(User::STATUS[$u['status']] ?? '') ?></span> <span class="tag"><?= e(User::ROLES[$u['role']] ?? '') ?></span></p>
        <dl class="info-list compact">
            <div><dt>E-mail</dt><dd><a href="mailto:<?= e($u['email']) ?>"><?= e($u['email']) ?></a></dd></div>
            <div><dt>Telefone</dt><dd><?= e($u['phone']) ?> <?php if ($wa = whatsapp_link($u['phone'])): ?><a href="<?= e($wa) ?>" target="_blank" rel="noopener noreferrer">WhatsApp ↗</a><?php endif; ?></dd></div>
            <div><dt>Cidade</dt><dd><?= e($u['city']) ?></dd></div>
            <div><dt>Nascimento</dt><dd><?= $u['birth_date'] ? e(date('d/m/Y', strtotime($u['birth_date']))) : '—' ?></dd></div>
            <div><dt>Moto</dt><dd><?= e(trim($u['bike_brand'] . ' ' . $u['bike_model'])) ?> <?= $u['bike_cc'] ? (int) $u['bike_cc'] . 'cc' : '' ?> <?= $u['bike_year'] ? '(' . (int) $u['bike_year'] . ')' : '' ?></dd></div>
            <div><dt>Nível</dt><dd><?= e(User::EXPERIENCE[$u['experience']] ?? '') ?></dd></div>
            <div><dt>Instagram</dt><dd><?= $u['instagram'] ? '@' . e($u['instagram']) : '—' ?></dd></div>
            <div class="sensitive"><dt>🩸 Tipo sanguíneo</dt><dd><?= e($u['blood_type'] ?: '—') ?></dd></div>
            <div class="sensitive"><dt>🚑 Emergência</dt><dd><?= e($u['emergency_name'] ?: '—') ?> <?= e($u['emergency_phone']) ?></dd></div>
            <div><dt>Cadastro</dt><dd><?= e(date('d/m/Y H:i', strtotime($u['created_at']))) ?></dd></div>
            <div><dt>2FA</dt><dd><?= $u['totp_enabled_at'] ? '✅ ativado' : '—' ?></dd></div>
            <div><dt>Último acesso</dt><dd><?= $u['last_login_at'] ? e(date('d/m/Y H:i', strtotime($u['last_login_at']))) : '—' ?></dd></div>
        </dl>
        <?php if ($u['bio']): ?><p class="prose"><?= nl2br_e($u['bio']) ?></p><?php endif; ?>
    </div>
</div>

<?php if ((int) $u['id'] !== $auth->id()): ?>
<div class="card actions">
    <form method="post" action="/admin/trilheiros/<?= (int) $u['id'] ?>/status" class="inline-form">
        <?= $csrf->field() ?>
        <select name="status"><?php foreach (User::STATUS as $k => $label): ?><option value="<?= e($k) ?>"<?= selected($k, $u['status']) ?>><?= e($label) ?></option><?php endforeach; ?></select>
        <button class="btn btn-sm" type="submit">Alterar situação</button>
    </form>
    <form method="post" action="/admin/trilheiros/<?= (int) $u['id'] ?>/perfil" class="inline-form" data-confirm="Alterar o perfil de acesso deste usuário?">
        <?= $csrf->field() ?>
        <select name="role"><?php foreach (User::ROLES as $k => $label): ?><option value="<?= e($k) ?>"<?= selected($k, $u['role']) ?>><?= e($label) ?></option><?php endforeach; ?></select>
        <button class="btn btn-sm" type="submit">Alterar perfil</button>
    </form>
    <?php if ($u['totp_enabled_at']): ?>
    <form method="post" action="/admin/trilheiros/<?= (int) $u['id'] ?>/2fa-reset" class="inline-form" data-confirm="Remover o 2FA deste usuário (ex.: perdeu o celular)?">
        <?= $csrf->field() ?><button class="btn btn-sm" type="submit">Resetar 2FA</button>
    </form>
    <?php endif; ?>
    <form method="post" action="/admin/trilheiros/<?= (int) $u['id'] ?>/excluir" class="inline-form" data-confirm="Excluir definitivamente este trilheiro e suas fotos?">
        <?= $csrf->field() ?>
        <button class="btn btn-sm btn-danger" type="submit">Excluir</button>
    </form>
</div>
<?php endif; ?>
