<section class="page-head">
    <div class="container narrow">
        <h1>Fale com a turma</h1>
        <p class="lead">Dúvidas, convites para trilhas, parcerias? Mande uma mensagem ou chame no Instagram <a href="https://www.instagram.com/<?= e($igUsername) ?>/" target="_blank" rel="noopener noreferrer">@<?= e($igUsername) ?></a>.</p>
    </div>
</section>
<section class="section">
    <div class="container narrow">
        <form class="card form" method="post" action="/contato">
            <?= $csrf->field() ?>
            <div class="hp" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
            <div class="grid-2">
                <label>Nome *
                    <input type="text" name="name" required maxlength="120" value="<?= e(old($old, 'name')) ?>">
                    <?= field_error($errors, 'name') ?>
                </label>
                <label>E-mail *
                    <input type="email" name="email" required maxlength="160" value="<?= e(old($old, 'email')) ?>">
                    <?= field_error($errors, 'email') ?>
                </label>
            </div>
            <label>Telefone
                <input type="tel" name="phone" maxlength="20" value="<?= e(old($old, 'phone')) ?>">
                <?= field_error($errors, 'phone') ?>
            </label>
            <label>Mensagem *
                <textarea name="message" rows="6" required maxlength="3000"><?= e(old($old, 'message')) ?></textarea>
                <?= field_error($errors, 'message') ?>
            </label>
            <button class="btn btn-primary" type="submit">Enviar mensagem</button>
        </form>
    </div>
</section>
