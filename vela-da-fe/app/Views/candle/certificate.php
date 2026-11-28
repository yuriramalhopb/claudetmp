<main class="certificate">
    <div class="cert-border">
        <p class="cert-church"><?= e($church['name']) ?></p>
        <h1>Certificado de Vela de Fé</h1>
        <div class="cert-candle"><?= $view->partial('candle', ['typeId' => $c['type_id'], 'lit' => true, 'size' => 'lg']) ?></div>
        <p class="cert-line">Certificamos que</p>
        <p class="cert-name"><?= e($c['name']) ?></p>
        <p class="cert-line">acendeu uma <strong><?= e($c['type_name']) ?></strong>
            <?php if (trim((string) $c['for_whom']) !== ''): ?>por <strong><?= e($c['for_whom']) ?></strong><?php endif; ?>
            em <?= e(date_br($c['lit_at'])) ?>, confiando a Deus a sua intenção.</p>
        <p class="cert-prayer"><?= nl2br_e((string) $settings->get('after_light_text')) ?></p>
        <p class="cert-code">Código da vela: <?= e($c['code']) ?></p>
    </div>
    <p class="no-print center"><button class="btn btn-gold" type="button" data-print>🖨️ Imprimir ou salvar em PDF</button>
        <a class="btn btn-ghost-dark" href="/vela/<?= e($c['code']) ?>/minha">Voltar</a></p>
</main>
