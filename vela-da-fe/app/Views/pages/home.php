<section class="hero">
    <div class="container hero-inner">
        <div class="hero-text">
            <p class="eyebrow">🕯️ <?= e($church['tagline']) ?></p>
            <h1><?= e($settings->get('hero_title')) ?></h1>
            <p class="lead"><?= nl2br_e((string) $settings->get('hero_text')) ?></p>
            <p class="hero-actions">
                <a class="btn btn-gold btn-lg" href="/acender">Acender minha vela</a>
                <a class="btn btn-ghost btn-lg" href="/velas">Ver o mural</a>
            </p>
            <ul class="counters" aria-label="Números do mural">
                <li><strong data-live-counter="lit_now"><?= (int) $counters['lit_now'] ?></strong><span>velas acesas agora</span></li>
                <li><strong data-live-counter="lit_total"><?= (int) $counters['lit_total'] ?></strong><span>já foram acesas</span></li>
                <li><strong data-live-counter="prayers"><?= (int) $counters['prayers'] ?></strong><span>orações em comunhão</span></li>
            </ul>
        </div>
        <div class="hero-candle" aria-hidden="true">
            <?= $view->partial('candle', ['typeId' => 0, 'lit' => true, 'size' => 'xl']) ?>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <h2 class="section-title">Como funciona</h2>
        <ol class="steps">
            <li><span class="step-num">1</span><h3>Escolha a sua vela</h3><p>Cor, duração e o motivo que está no seu coração.</p></li>
            <li><span class="step-num">2</span><h3>Confie a sua intenção</h3><p>Escreva o seu pedido ou agradecimento. Você decide o que aparece para os outros.</p></li>
            <li><span class="step-num">3</span><h3>A comunidade reza com você</h3><p>Sua vela fica acesa no mural e as pessoas podem rezar por ela.</p></li>
        </ol>
    </div>
</section>

<?php if ($types): ?>
<section class="section section-alt">
    <div class="container">
        <h2 class="section-title">Escolha o tipo de vela</h2>
        <ul class="type-grid">
            <?php foreach ($types as $t): ?>
            <li>
                <a class="type-tile" href="/acender?tipo=<?= (int) $t['id'] ?>">
                    <?= $view->partial('candle', ['typeId' => $t['id'], 'lit' => true]) ?>
                    <strong><?= e($t['name']) ?></strong>
                    <?php if ($t['description']): ?><span><?= e($t['description']) ?></span><?php endif; ?>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>
<?php endif; ?>

<section class="section">
    <div class="container">
        <div class="section-head">
            <h2 class="section-title">Acesas agora no mural</h2>
            <a href="/velas">Ver todas →</a>
        </div>
        <?php if ($latest): ?>
            <div class="candle-grid">
                <?php foreach ($latest as $c): ?><?= $view->partial('candle-card', ['c' => $c]) ?><?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="empty">O mural está vazio por enquanto. <a href="/acender">Seja a primeira pessoa a acender uma vela!</a></p>
        <?php endif; ?>
    </div>
</section>

<?php if ($message || $dayPrayer): ?>
<section class="section section-alt">
    <div class="container two-col">
        <?php if ($message): ?>
        <article class="card quote-card">
            <p class="eyebrow-dark">Palavra do dia</p>
            <blockquote>“<?= e($message['text']) ?>”</blockquote>
            <?php if ($message['reference']): ?><p class="quote-ref">— <?= e($message['reference']) ?></p><?php endif; ?>
        </article>
        <?php endif; ?>
        <?php if ($dayPrayer): ?>
        <article class="card">
            <p class="eyebrow-dark">Oração do dia</p>
            <h3><?= e($dayPrayer['title']) ?></h3>
            <p class="prayer-excerpt"><?= nl2br_e(excerpt($dayPrayer['body'], 260)) ?></p>
            <p><a href="/oracoes/<?= e($dayPrayer['slug']) ?>">Ler a oração completa →</a></p>
        </article>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<?php if ($massTimes): ?>
<section class="section">
    <div class="container two-col">
        <div>
            <h2 class="section-title left">Horários das celebrações</h2>
            <table class="times">
                <?php foreach ($massTimes as $wd => $rows): ?>
                    <tr>
                        <th scope="row"><?= e(App\Admin\Resources::WEEKDAYS[$wd] ?? '') ?></th>
                        <td><?= e(implode(' · ', array_map(static fn ($r) => $r['time'], $rows))) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
            <p><a href="/igreja">Endereço e contatos →</a></p>
        </div>
        <?php if ($settings->bool('mass_enabled')): ?>
        <div class="card highlight-card">
            <h3>🕊️ Lembrar na Santa Missa</h3>
            <p><?= e($settings->get('mass_note')) ?></p>
            <p><a class="btn btn-gold" href="/acender">Acender e confiar a intenção</a></p>
        </div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<?php if ($testimonials): ?>
<section class="section section-alt">
    <div class="container">
        <div class="section-head">
            <h2 class="section-title">Graças alcançadas</h2>
            <a href="/gracas">Ler mais →</a>
        </div>
        <div class="three-col">
            <?php foreach ($testimonials as $t): ?>
                <article class="card"><p>“<?= e(excerpt($t['text'], 220)) ?>”</p><p class="muted">— <?= e($t['name']) ?></p></article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="cta-band">
    <div class="container center">
        <h2>Há uma intenção no seu coração?</h2>
        <p>Acenda uma vela agora. É simples, e a comunidade reza com você.</p>
        <a class="btn btn-gold btn-lg" href="/acender">🕯️ Acender uma vela</a>
    </div>
</section>
