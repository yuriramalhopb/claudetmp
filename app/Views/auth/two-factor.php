<section class="section">
    <div class="container tiny">
        <form class="card form" method="post" action="/entrar/2fa" autocomplete="off">
            <h1>🔐 Verificação</h1>
            <p class="muted">Abra o app autenticador (Google Authenticator, Authy…) e digite o código de 6 dígitos da <strong>Ducks Offroad</strong>.</p>
            <?= $csrf->field() ?>
            <label>Código
                <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="20" required autofocus placeholder="123 456" class="code-input">
            </label>
            <button class="btn btn-primary btn-block" type="submit">Confirmar</button>
            <p class="hint">Perdeu o celular? Digite um dos seus <strong>códigos de recuperação</strong> (formato ABCD-EFGH) no mesmo campo.</p>
        </form>
    </div>
</section>
