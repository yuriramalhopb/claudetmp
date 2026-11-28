<?php $shareUrl = $app->config->get('app.url') . '/vela/' . $c['code']; ?>
<section class="section candle-page">
    <div class="container narrow center">
        <p><a href="/velas">← Mural de velas</a></p>
        <div class="big-candle<?= $c['lit'] ? '' : ' is-off' ?>">
            <?= $view->partial('candle', ['typeId' => $c['type_id'], 'lit' => $c['lit'], 'size' => 'xl']) ?>
        </div>
        <h1><?= $c['lit'] ? 'Vela acesa' : 'Vela que já se apagou' ?></h1>
        <p class="lead"><?= $c['display_name'] === 'Anônimo' ? 'Alguém' : e($c['display_name']) ?> confiou a Deus uma intenção.</p>
        <p class="tags">
            <span class="tag"><?= e($c['category_emoji'] . ' ' . $c['category_label']) ?></span>
            <span class="tag"><?= e($c['type_name']) ?></span>
        </p>

        <?php if ($c['display_whom'] !== null): ?><p class="whom">Por <strong><?= e($c['display_whom']) ?></strong></p><?php endif; ?>
        <?php if ($c['display_text'] !== null): ?>
            <blockquote class="intention">“<?= nl2br_e($c['display_text']) ?>”</blockquote>
        <?php elseif ($c['text_pending']): ?>
            <p class="muted">Uma intenção foi confiada a Deus. 🙏</p>
        <?php endif; ?>

        <p class="remaining">
            <?php if ($c['lit']): ?>
                Acesa há <?= e(ago_human($c['lit_at'])) ?> · restam
                <strong data-countdown="<?= e(date('c', strtotime((string) $c['expires_at']))) ?>"><?= e(remaining_human($c['expires_at'])) ?></strong>
            <?php else: ?>
                Apagou-se em <?= e(date_br($c['expires_at'], true)) ?>
            <?php endif; ?>
        </p>

        <div class="pray-box">
            <p class="pray-count"><span data-pray-count><?= (int) $c['prayer_count'] ?></span> <?= (int) $c['prayer_count'] === 1 ? 'pessoa rezou' : 'pessoas rezaram' ?> por esta intenção</p>
            <form method="post" action="/vela/<?= e($c['code']) ?>/rezar" data-pray-form>
                <?= $csrf->field() ?>
                <button class="btn btn-gold btn-lg" type="submit" data-pray-btn<?= $alreadyPrayed ? ' disabled' : '' ?>><?= $alreadyPrayed ? '🙏 Você já rezou hoje' : '🙏 Rezar por esta intenção' ?></button>
            </form>
        </div>

        <p class="actions">
            <a class="btn btn-ghost-dark" href="<?= e('https://wa.me/?text=' . rawurlencode('Acendi uma vela de fé. Reze junto e acenda a sua também: ' . $shareUrl)) ?>" target="_blank" rel="noopener noreferrer">💬 Compartilhar no WhatsApp</a>
            <button class="btn btn-ghost-dark" type="button" data-share data-share-title="Vela de fé" data-share-url="<?= e($shareUrl) ?>">🔗 Compartilhar</button>
            <a class="btn btn-gold" href="/acender">🕯️ Acender a minha vela</a>
        </p>
        <?php if ($owner): ?><p class="muted"><a href="/vela/<?= e($c['code']) ?>/minha">Esta é a sua vela: gerenciar</a></p><?php endif; ?>
    </div>
</section>
