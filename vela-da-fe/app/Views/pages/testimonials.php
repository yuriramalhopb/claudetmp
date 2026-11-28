<section class="section">
    <div class="container narrow">
        <h1 class="center">Graças alcançadas</h1>
        <p class="lead center">Testemunhos de quem confiou a Deus uma intenção e quer partilhar a sua fé.</p>

        <?php foreach ($items as $t): ?>
            <article class="card testimonial">
                <p>“<?= nl2br_e($t['text']) ?>”</p>
                <p class="muted">— <?= e($t['name']) ?> · <?= e(date_br($t['created_at'])) ?></p>
            </article>
        <?php endforeach; ?>
        <?php if (!$items): ?><p class="empty">Ainda não há testemunhos publicados. Seja o primeiro a partilhar!</p><?php endif; ?>
        <?php require __DIR__ . '/../partials/pagination.php'; ?>

        <form class="card form" id="enviar" method="post" action="/gracas">
            <?= $csrf->field() ?>
            <h2>Partilhe a sua graça</h2>
            <p class="muted">Seu texto será publicado depois de lido pela nossa equipe.</p>
            <p class="hp" aria-hidden="true"><label>Não preencha <input type="text" name="website" tabindex="-1" autocomplete="off"></label></p>
            <div class="field">
                <label for="t-name">Seu nome (ou apenas as iniciais)</label>
                <input id="t-name" name="name" maxlength="80" required value="<?= e(old($old, 'name')) ?>">
                <?= field_error($errors, 'name') ?>
            </div>
            <div class="field">
                <label for="t-text">Seu testemunho</label>
                <textarea id="t-text" name="text" rows="5" maxlength="1500" required data-counter><?= e(old($old, 'text')) ?></textarea>
                <?= field_error($errors, 'text') ?>
            </div>
            <button class="btn btn-gold" type="submit">Enviar testemunho</button>
        </form>
    </div>
</section>
