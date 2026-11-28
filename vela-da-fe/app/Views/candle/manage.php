<?php
$code = $c['code'];
$pending = $raw['status'] === 'pending_payment';
$cancelled = in_array($raw['status'], ['cancelled', 'removed'], true);
$wa = whatsapp_link($church['whatsapp'], 'Olá! Segue o comprovante do Pix da vela ' . $code . '.');
?>
<section class="section candle-page">
    <div class="container narrow center">
        <div class="big-candle<?= $c['lit'] ? ' is-new' : ' is-off' ?>">
            <?= $view->partial('candle', ['typeId' => $c['type_id'], 'lit' => $c['lit'], 'size' => 'xl']) ?>
        </div>

        <?php if ($pending): ?>
            <h1>Falta pouco, <?= e(strtok($raw['name'], ' ')) ?>!</h1>
            <p class="lead">Faça o Pix abaixo para acender a sua vela.</p>
        <?php elseif ($cancelled): ?>
            <h1>Esta vela não está acesa</h1>
            <p class="lead"><?= $raw['status'] === 'removed' ? 'Ela foi retirada pela moderação.' : 'O pedido foi cancelado.' ?></p>
        <?php elseif ($c['lit']): ?>
            <h1><?= $isNew ? 'Sua vela está acesa! 🕯️' : 'Sua vela' ?></h1>
            <p class="lead">Ela ficará acesa por mais <strong data-countdown="<?= e(date('c', strtotime((string) $raw['expires_at']))) ?>"><?= e(remaining_human($raw['expires_at'])) ?></strong>
                (até <?= e(date_br($raw['expires_at'], true)) ?>).</p>
        <?php else: ?>
            <h1>Sua vela se apagou</h1>
            <p class="lead">Ela cumpriu o seu tempo em <?= e(date_br($raw['expires_at'], true)) ?>. Que a luz de Deus continue com você.</p>
        <?php endif; ?>

        <?php if ($c['lit'] && $isNew): ?>
            <blockquote class="intention after-light"><?= nl2br_e((string) $settings->get('after_light_text')) ?></blockquote>
        <?php endif; ?>

        <?php if ($raw['mod_status'] === 'pending'): ?>
            <p class="notice">Seu nome/intenção estão em análise pela moderação e aparecerão no mural assim que forem aprovados. A vela já está acesa. 🕯️</p>
        <?php elseif ($raw['mod_status'] === 'rejected'): ?>
            <p class="notice">O texto desta vela não pôde ser publicado, então ela aparece no mural de forma anônima.</p>
        <?php endif; ?>

        <?php if ($pending): ?>
            <div class="card pix-card">
                <p class="pix-amount"><?= e(money((int) $raw['amount'])) ?></p>
                <?php if (!empty($pixPayload)): ?>
                    <div class="qr" data-qr="<?= e($pixPayload) ?>" data-qr-size="220" data-qr-alt="QR Code do Pix"></div>
                    <label class="pix-label" for="pix-code">Pix copia e cola</label>
                    <textarea id="pix-code" class="pix-code" readonly rows="3"><?= e($pixPayload) ?></textarea>
                    <p><button class="btn btn-gold" type="button" data-copy="#pix-code">Copiar código Pix</button></p>
                <?php else: ?>
                    <p class="notice">O Pix não está disponível no momento. Fale com a secretaria.</p>
                <?php endif; ?>
                <p class="muted"><?= nl2br_e((string) $settings->get('pix_instructions')) ?></p>
                <?php if ($raw['paid_claimed_at']): ?>
                    <p class="notice">✔ Você avisou que pagou em <?= e(datetime_short($raw['paid_claimed_at'])) ?>. Estamos conferindo.</p>
                <?php else: ?>
                    <form method="post" action="/vela/<?= e($code) ?>/paguei">
                        <?= $csrf->field() ?><input type="hidden" name="t" value="<?= e($token) ?>">
                        <button class="btn btn-gold btn-lg" type="submit">✔ Já fiz o Pix</button>
                    </form>
                <?php endif; ?>
                <?php if ($wa): ?><p><a class="btn btn-ghost-dark btn-sm" href="<?= e($wa) ?>" target="_blank" rel="noopener noreferrer">💬 Enviar comprovante pelo WhatsApp</a></p><?php endif; ?>
                <form method="post" action="/vela/<?= e($code) ?>/cancelar" data-confirm="Cancelar este pedido de vela?">
                    <?= $csrf->field() ?><input type="hidden" name="t" value="<?= e($token) ?>">
                    <button class="link-button" type="submit">Cancelar pedido</button>
                </form>
            </div>
        <?php endif; ?>

        <?php if ($raw['status'] === 'active'): ?>
            <dl class="facts">
                <div><dt>Tipo</dt><dd><?= e($raw['type_name']) ?></dd></div>
                <div><dt>Duração</dt><dd><?= e(hours_human((int) $raw['hours'])) ?></dd></div>
                <div><dt>Orações recebidas</dt><dd>🙏 <?= (int) $raw['prayer_count'] ?></dd></div>
                <?php if ($raw['mass_intention']): ?><div><dt>Santa Missa</dt><dd>🕊️ Intenção enviada à equipe</dd></div><?php endif; ?>
            </dl>
            <p class="actions">
                <a class="btn btn-gold" href="/vela/<?= e($code) ?>">Ver minha vela no mural</a>
                <a class="btn btn-ghost-dark" href="<?= e('https://wa.me/?text=' . rawurlencode('Acendi uma vela de fé. Reze junto e acenda a sua também: ' . $publicUrl)) ?>" target="_blank" rel="noopener noreferrer">💬 Compartilhar</a>
                <a class="btn btn-ghost-dark" href="/vela/<?= e($code) ?>/certificado?t=<?= e($token) ?>" target="_blank">🖨️ Certificado</a>
                <a class="btn btn-ghost-dark" href="/acender?repetir=<?= e($code) ?>&amp;t=<?= e($token) ?>">🔁 Acender novamente</a>
            </p>
        <?php endif; ?>

        <?php if (!$pending && !$cancelled): ?>
        <div class="card manage-link">
            <p><strong>Guarde este endereço</strong>: é o seu acesso privado a esta vela (você também pode receber por e-mail em <a href="/minhas-velas">Minhas velas</a>).</p>
            <input id="manage-url" class="copy-input" readonly value="<?= e($manageUrl) ?>">
            <p><button class="btn btn-ghost-dark btn-sm" type="button" data-copy="#manage-url">Copiar endereço</button></p>
        </div>
        <?php endif; ?>
        <p class="muted"><a href="/oracoes">Rezar uma oração</a> · <a href="/velas">Mural de velas</a></p>
    </div>
</section>
