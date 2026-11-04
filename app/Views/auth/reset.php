<section class="section">
    <div class="container tiny">
        <form class="card form" method="post" action="/redefinir-senha">
            <h1>Nova senha</h1>
            <?= $csrf->field() ?>
            <input type="hidden" name="token" value="<?= e($token) ?>">
            <label>Nova senha <small>(8+ caracteres, letras e números)</small>
                <input type="password" name="password" required minlength="8" maxlength="128" autocomplete="new-password">
                <?= field_error($errors, 'password') ?>
            </label>
            <label>Confirme a nova senha
                <input type="password" name="password_confirmation" required minlength="8" maxlength="128" autocomplete="new-password">
            </label>
            <button class="btn btn-primary btn-block" type="submit">Salvar nova senha</button>
        </form>
    </div>
</section>
