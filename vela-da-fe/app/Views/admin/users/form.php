<?php $v = $old + ($u ?? []); $action = $u ? '/admin/equipe/' . (int) $u['id'] : '/admin/equipe'; ?>
<form class="panel admin-form" method="post" action="<?= e($action) ?>" autocomplete="off">
    <?= $csrf->field() ?>
    <div class="field"><label for="name">Nome</label><input id="name" name="name" required maxlength="120" value="<?= e(old($v, 'name')) ?>"><?= field_error($errors, 'name') ?></div>
    <div class="field"><label for="email">E-mail (usado para entrar)</label><input id="email" name="email" type="email" required value="<?= e(old($v, 'email')) ?>"><?= field_error($errors, 'email') ?></div>
    <div class="field"><label for="role">Perfil</label>
        <select id="role" name="role"><?php foreach (App\Models\User::ROLES as $k => $l): ?><option value="<?= e($k) ?>"<?= selected(old($v, 'role', 'moderator'), $k) ?>><?= e($l) ?></option><?php endforeach; ?></select>
        <?= field_error($errors, 'role') ?></div>
    <?php if ($u): ?>
    <div class="field"><label for="status">Acesso</label>
        <select id="status" name="status"><?php foreach (App\Models\User::STATUS as $k => $l): ?><option value="<?= e($k) ?>"<?= selected(old($v, 'status', 'active'), $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <?php endif; ?>
    <div class="field"><label for="password"><?= $u ? 'Nova senha (deixe em branco para manter)' : 'Senha inicial' ?></label>
        <input id="password" name="password" type="password" autocomplete="new-password" <?= $u ? '' : 'required' ?>><?= field_error($errors, 'password') ?>
        <p class="field-help">Mínimo de 8 caracteres, com letras e números.</p></div>
    <div class="field"><label for="password_confirmation">Confirmar senha</label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password"></div>
    <button class="btn btn-gold" type="submit">Salvar</button> <a class="btn btn-ghost-dark" href="/admin/equipe">Cancelar</a>
</form>
