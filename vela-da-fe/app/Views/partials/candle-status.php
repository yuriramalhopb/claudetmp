<?php /** @var array $c linha da tabela candles (crua) */
$lit = $c['status'] === 'active' && strtotime((string) $c['expires_at']) > time();
?>
<?php if ($c['status'] === 'pending_payment'): ?>
    <span class="badge <?= $c['paid_claimed_at'] ? 'badge-info' : 'badge-warn' ?>"><?= $c['paid_claimed_at'] ? 'Informou que pagou' : 'Aguardando Pix' ?></span>
<?php elseif ($c['status'] === 'active'): ?>
    <span class="badge <?= $lit ? 'badge-ok' : '' ?>"><?= $lit ? 'Acesa' : 'Apagada' ?></span>
<?php else: ?>
    <span class="badge badge-bad"><?= e(App\Models\Candle::STATUS[$c['status']] ?? $c['status']) ?></span>
<?php endif; ?>
<?php if ($c['mod_status'] === 'pending'): ?><span class="badge badge-warn">Moderar</span><?php elseif ($c['mod_status'] === 'rejected'): ?><span class="badge badge-bad">Texto recusado</span><?php endif; ?>
<?php if ($c['mass_intention']): ?><span class="badge" title="Intenção para a missa">🕊️ Missa<?= $c['mass_done_at'] ? ' ✔' : '' ?></span><?php endif; ?>
