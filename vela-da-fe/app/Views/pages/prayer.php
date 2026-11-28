<section class="section">
    <div class="container narrow">
        <p><a href="/oracoes">← Todas as orações</a></p>
        <article class="card prayer-full">
            <h1><?= e($prayer['title']) ?></h1>
            <div class="prayer-body"><?= nl2br_e($prayer['body']) ?></div>
            <p class="actions">
                <button class="btn btn-ghost-dark btn-sm" type="button" data-print>🖨️ Imprimir</button>
                <button class="btn btn-ghost-dark btn-sm" type="button" data-share data-share-title="<?= e($prayer['title']) ?>">🔗 Compartilhar</button>
                <a class="btn btn-gold btn-sm" href="/acender">🕯️ Acender uma vela</a>
            </p>
        </article>
    </div>
</section>
