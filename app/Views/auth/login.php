<section class="section">
    <div class="container tiny">
        <form class="card form" method="post" action="/entrar">
            <h1>Entrar</h1>
            <?= $csrf->field() ?>
            <label>E-mail
                <input type="email" name="email" required autocomplete="username" value="<?= e(old($old, 'email')) ?>" autofocus>
            </label>
            <label>Senha
                <input type="password" name="password" required autocomplete="current-password">
            </label>
            <?= field_error($errors, 'email') ?>
            <button class="btn btn-primary btn-block" type="submit">Entrar</button>
            <p class="hint"><a href="/esqueci-senha">Esqueci minha senha</a> · <a href="/cadastro">Criar cadastro</a></p>
        </form>
    </div>
</section>
