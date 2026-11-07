<section class="page-head">
    <div class="container narrow">
        <p class="breadcrumb"><a href="/minha-conta">← Minha conta</a></p>
        <h1>🔐 Verificação em duas etapas</h1>
        <p class="lead">Além da senha, o login pede um código que muda a cada 30 segundos no seu celular. Mesmo que alguém descubra sua senha, não consegue entrar.</p>
    </div>
</section>
<section class="section">
    <div class="container narrow">
    <?php if ($enabled): ?>
        <div class="card">
            <p class="notice notice-success">✅ Ativada desde <?= e(date_br($user['totp_enabled_at'], true)) ?>.</p>
            <p>Códigos de recuperação restantes: <strong><?= (int) $recoveryLeft ?></strong> de 10.</p>
            <form class="form" method="post" action="/minha-conta/2fa/desativar" data-confirm="Desativar a verificação em duas etapas?">
                <h2>Desativar</h2>
                <?= $csrf->field() ?>
                <div class="grid-2">
                    <label>Senha <input type="password" name="password" required autocomplete="current-password"></label>
                    <label>Código atual do app <input type="text" name="code" inputmode="numeric" required maxlength="7" autocomplete="one-time-code"></label>
                </div>
                <button class="btn btn-danger" type="submit">Desativar 2FA</button>
                <?php if ($user['role'] === 'admin'): ?><p class="hint">Administradores precisam do 2FA para acessar o painel.</p><?php endif; ?>
            </form>
        </div>
    <?php else: ?>
        <div class="card two-factor-setup">
            <ol class="steps">
                <li>Instale um app autenticador: <strong>Google Authenticator</strong>, <strong>Microsoft Authenticator</strong> ou <strong>Authy</strong>.</li>
                <li>No app, toque em <strong>+</strong> e escaneie o QR Code:
                    <div class="qr-box" data-qr="<?= e($uri) ?>" data-qr-alt="QR Code para o app autenticador">Carregando QR Code…</div>
                    <details><summary>Não consegue escanear? Digite a chave</summary>
                        <p><code class="secret"><?= e(trim(chunk_split($secret, 4, ' '))) ?></code></p>
                    </details>
                </li>
                <li>Digite o código de 6 dígitos que aparece no app:</li>
            </ol>
            <form class="form" method="post" action="/minha-conta/2fa/ativar" autocomplete="off">
                <?= $csrf->field() ?>
                <label>Código <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="7" required class="code-input" placeholder="123 456"></label>
                <button class="btn btn-primary" type="submit">Ativar verificação em duas etapas</button>
            </form>
        </div>
    <?php endif; ?>
    </div>
</section>
