<main class="login-page">
    <form class="card form login-card" method="post" action="/admin/entrar">
        <?= $csrf->field() ?>
        <p class="center"><svg viewBox="0 0 32 32" width="44" height="44" aria-hidden="true"><path d="M16 2c2.6 3 4 5.3 4 7.6A4 4 0 0 1 16 14a4 4 0 0 1-4-4.4C12 7.3 13.4 5 16 2z" fill="#f3c969"/><rect x="12" y="15" width="8" height="15" rx="2" fill="#fbf7ee" stroke="#d9cfb8"/><rect x="15.3" y="13" width="1.4" height="3" fill="#6b6480"/></svg></p>
        <h1 class="center">Painel da equipe</h1>
        <p class="center muted"><?= e($church['name']) ?></p>
        <div class="field">
            <label for="email">E-mail</label>
            <input id="email" name="email" type="email" required autocomplete="username" autofocus value="<?= e(old($old, 'email')) ?>">
        </div>
        <div class="field">
            <label for="password">Senha</label>
            <input id="password" name="password" type="password" required autocomplete="current-password">
        </div>
        <button class="btn btn-gold btn-block" type="submit">Entrar</button>
        <p class="center"><a href="/">← Voltar ao site</a></p>
    </form>
</main>
