<section class="page-head">
    <div class="container narrow"><h1>Editar perfil</h1><p class="breadcrumb"><a href="/minha-conta">← Minha conta</a></p></div>
</section>
<section class="section">
    <div class="container narrow stack">
        <form class="card form photo-form" method="post" action="/minha-conta/foto" enctype="multipart/form-data">
            <?= $csrf->field() ?>
            <img class="profile-photo sm" src="<?= e(avatar_url($user)) ?>" alt="">
            <label>Foto de perfil
                <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required>
            </label>
            <button class="btn" type="submit">Atualizar foto</button>
        </form>

        <form class="card form" method="post" action="/minha-conta/editar">
            <?= $csrf->field() ?>
            <?= $view->partial('profile-fields', ['errors' => $errors, 'old' => $old, 'user' => $user]) ?>
            <button class="btn btn-primary" type="submit">Salvar alterações</button>
        </form>

        <form class="card form" method="post" action="/minha-conta/senha">
            <h2>Trocar senha</h2>
            <?= $csrf->field() ?>
            <label>Senha atual
                <input type="password" name="current_password" required autocomplete="current-password">
                <?= field_error($errors, 'current_password') ?>
            </label>
            <div class="grid-2">
                <label>Nova senha
                    <input type="password" name="password" required minlength="8" maxlength="128" autocomplete="new-password">
                    <?= field_error($errors, 'password') ?>
                </label>
                <label>Confirme a nova senha
                    <input type="password" name="password_confirmation" required minlength="8" maxlength="128" autocomplete="new-password">
                </label>
            </div>
            <button class="btn" type="submit">Trocar senha</button>
        </form>

        <div class="card form danger-zone">
            <h2>Meus dados (LGPD)</h2>
            <p class="muted">Você pode baixar uma cópia dos seus dados ou excluir sua conta definitivamente.</p>
            <a class="btn btn-ghost" href="/minha-conta/meus-dados.json">Baixar meus dados</a>
            <form method="post" action="/minha-conta/excluir" data-confirm="Excluir sua conta definitivamente? Esta ação não pode ser desfeita.">
                <?= $csrf->field() ?>
                <label>Confirme sua senha para excluir a conta
                    <input type="password" name="password" required autocomplete="current-password">
                </label>
                <button class="btn btn-danger" type="submit">Excluir minha conta</button>
            </form>
        </div>
    </div>
</section>
