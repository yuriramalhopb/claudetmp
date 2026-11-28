<section class="section">
    <div class="container narrow">
        <h1 class="center">Minhas velas</h1>
        <p class="lead center">Perdeu o link da sua vela? Informe o e-mail que você usou ao acendê-la e enviaremos os links das velas recentes.</p>
        <form class="card form" method="post" action="/minhas-velas">
            <?= $csrf->field() ?>
            <p class="hp" aria-hidden="true"><label>Não preencha <input type="text" name="website" tabindex="-1" autocomplete="off"></label></p>
            <div class="field">
                <label for="email">Seu e-mail</label>
                <input id="email" name="email" type="email" required autocomplete="email" value="<?= e(old($old, 'email')) ?>">
                <?= field_error($errors, 'email') ?>
            </div>
            <button class="btn btn-gold" type="submit">Enviar os links</button>
            <p class="hint">Por segurança, cada envio gera um novo link e o anterior deixa de valer.</p>
        </form>
    </div>
</section>
