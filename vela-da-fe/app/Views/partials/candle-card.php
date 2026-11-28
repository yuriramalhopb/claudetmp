<?php /** @var array $c vela já tratada por Candle::present() */ ?>
<a class="candle-card<?= $c['lit'] ? '' : ' is-off' ?>" href="/vela/<?= e($c['code']) ?>">
    <?= $view->partial('candle', ['typeId' => $c['type_id'], 'lit' => $c['lit'], 'size' => 'sm']) ?>
    <span class="cc-body">
        <span class="cc-name"><?= e($c['display_name']) ?></span>
        <span class="cc-cat"><?= e($c['category_emoji'] . ' ' . $c['category_label']) ?></span>
        <?php if ($c['display_text'] !== null): ?>
            <span class="cc-text">“<?= e(excerpt($c['display_text'], 90)) ?>”</span>
        <?php elseif ($c['text_pending']): ?>
            <span class="cc-text muted">Uma intenção foi confiada a Deus. 🙏</span>
        <?php elseif ($c['display_whom'] !== null): ?>
            <span class="cc-text">Por <?= e(excerpt($c['display_whom'], 60)) ?></span>
        <?php endif; ?>
        <span class="cc-meta">
            <?= $c['lit'] ? 'Acesa · restam ' . e(remaining_human($c['expires_at'])) : 'Apagada em ' . e(date('d/m', strtotime((string) $c['expires_at']))) ?>
            · 🙏 <?= (int) $c['prayer_count'] ?>
        </span>
    </span>
</a>
