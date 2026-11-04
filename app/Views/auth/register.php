<section class="page-head">
    <div class="container narrow">
        <h1>Cadastro de trilheiro</h1>
        <p class="lead">Preencha seus dados para entrar na turma. Após a aprovação você poderá confirmar presença nas trilhas e enviar fotos.</p>
    </div>
</section>
<section class="section">
    <div class="container narrow">
        <form class="card form" method="post" action="/cadastro" enctype="multipart/form-data" novalidate>
            <?= $csrf->field() ?>
            <div class="hp" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
            <fieldset>
                <legend>Acesso</legend>
                <label>E-mail *
                    <input type="email" name="email" required maxlength="160" autocomplete="email" value="<?= e(old($old, 'email')) ?>">
                    <?= field_error($errors, 'email') ?>
                </label>
                <div class="grid-2">
                    <label>Senha * <small>(8+ caracteres, letras e números)</small>
                        <input type="password" name="password" required minlength="8" maxlength="128" autocomplete="new-password">
                        <?= field_error($errors, 'password') ?>
                    </label>
                    <label>Confirme a senha *
                        <input type="password" name="password_confirmation" required minlength="8" maxlength="128" autocomplete="new-password">
                    </label>
                </div>
            </fieldset>
            <?= $view->partial('profile-fields', ['errors' => $errors, 'old' => $old, 'user' => null]) ?>
            <label>Foto de perfil (opcional)
                <input type="file" name="photo" accept="image/jpeg,image/png,image/webp">
            </label>
            <label class="check">
                <input type="checkbox" name="lgpd" value="1" required<?= checked(old($old, 'lgpd')) ?>>
                Li e concordo com a <a href="/privacidade" target="_blank">política de privacidade</a> e autorizo o uso dos meus dados para a organização das trilhas. *
            </label>
            <?= field_error($errors, 'lgpd') ?>
            <button class="btn btn-primary btn-lg" type="submit">Enviar cadastro</button>
            <p class="hint">Já tem conta? <a href="/entrar">Entrar</a></p>
        </form>
    </div>
</section>
