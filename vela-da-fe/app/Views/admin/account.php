<?php $v = $old + $me; ?>
<div class="grid-2">
<form class="panel admin-form" method="post" action="/admin/conta">
    <?= $csrf->field() ?>
    <h2>Meus dados</h2>
    <div class="field"><label for="name">Nome</label><input id="name" name="name" required maxlength="120" value="<?= e(old($v, 'name')) ?>"><?= field_error($errors, 'name') ?></div>
    <div class="field"><label for="email">E-mail</label><input id="email" name="email" type="email" required value="<?= e(old($v, 'email')) ?>"><?= field_error($errors, 'email') ?></div>
    <button class="btn btn-gold" type="submit">Salvar</button>
</form>
<form class="panel admin-form" method="post" action="/admin/conta/senha" autocomplete="off">
    <?= $csrf->field() ?>
    <h2>Trocar a senha</h2>
    <div class="field"><label for="current_password">Senha atual</label><input id="current_password" name="current_password" type="password" required autocomplete="current-password"><?= field_error($errors, 'current_password') ?></div>
    <div class="field"><label for="password">Nova senha</label><input id="password" name="password" type="password" required autocomplete="new-password"><?= field_error($errors, 'password') ?></div>
    <div class="field"><label for="password_confirmation">Repita a nova senha</label><input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"></div>
    <button class="btn btn-gold" type="submit">Trocar senha</button>
</form>
</div>
