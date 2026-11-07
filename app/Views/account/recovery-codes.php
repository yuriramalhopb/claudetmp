<section class="section">
    <div class="container narrow">
        <div class="card">
            <h1>✅ 2FA ativado!</h1>
            <p class="notice notice-warning"><strong>Guarde estes códigos de recuperação agora.</strong> Eles só aparecem esta vez. Cada um funciona uma única vez, caso você perca o celular.</p>
            <ul class="recovery-codes">
                <?php foreach ($codes as $c): ?><li><code><?= e($c) ?></code></li><?php endforeach; ?>
            </ul>
            <textarea id="recovery-text" class="sr-only" readonly><?= e(implode("\n", $codes)) ?></textarea>
            <div class="actions">
                <button class="btn" type="button" data-copy="#recovery-text">Copiar códigos</button>
                <a class="btn btn-primary" href="<?= $auth->isAdmin() ? '/admin' : '/minha-conta' ?>">Já guardei, continuar</a>
            </div>
        </div>
    </div>
</section>
