<section class="section">
    <div class="container tiny">
        <form class="card form" method="post" action="/esqueci-senha">
            <h1>Recuperar senha</h1>
            <p class="muted">Informe seu e-mail e enviaremos um link para criar uma nova senha.</p>
            <?= $csrf->field() ?>
            <label>E-mail
                <input type="email" name="email" required autocomplete="email" value="<?= e(old($old, 'email')) ?>">
                <?= field_error($errors, 'email') ?>
            </label>
            <button class="btn btn-primary btn-block" type="submit">Enviar link</button>
            <p class="hint"><a href="/entrar">Voltar</a></p>
        </form>
    </div>
</section>
