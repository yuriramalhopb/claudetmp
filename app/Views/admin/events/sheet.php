<?php use App\Models\Event; use App\Models\User; ?>
<div class="admin-head no-print">
    <p class="breadcrumb"><a href="/admin/eventos">← Eventos</a></p>
    <div class="actions">
        <a class="btn btn-sm btn-ghost" href="/admin/eventos/<?= (int) $event['id'] ?>/ficha.csv">⬇ Planilha</a>
        <button class="btn btn-sm btn-primary" type="button" data-print>🖨️ Imprimir ficha</button>
    </div>
</div>
<div class="sheet">
    <header class="sheet-head">
        <h1>Ficha de segurança — <?= e($event['title']) ?></h1>
        <p><?= e(ucfirst(weekday_br($event['start_at']))) ?>, <?= e(date_br($event['start_at'], true)) ?> · <?= e(trim($event['city'] . ($event['state'] ? '/' . $event['state'] : ''))) ?><?= $event['location'] ? ' · ' . e($event['location']) : '' ?></p>
        <p class="sheet-totals"><strong><?= (int) $totals['confirmed'] ?></strong> confirmados
            · <strong><?= (int) $totals['attended'] ?></strong> presentes
            <?php if ((float) $event['pix_amount'] > 0): ?>· <strong><?= (int) $totals['paid'] ?></strong> pagos (R$ <?= e(number_format($totals['amount'], 2, ',', '.')) ?>)<?php if ($totals['informed']): ?> · <strong><?= (int) $totals['informed'] ?></strong> a conferir<?php endif; ?><?php endif; ?>
        </p>
        <p class="sheet-warning">⚠️ Documento com dados pessoais e de saúde (LGPD). Uso exclusivo da organização para socorro. Não compartilhe.</p>
    </header>
    <?php if ($rows): ?>
    <div class="table-wrap">
    <table class="table sheet-table">
        <thead><tr><th>#</th><th>Trilheiro</th><th>Telefone</th><th>Moto</th><th>🩸</th><th>Emergência</th><th>Termo</th><?php if ((float) $event['pix_amount'] > 0): ?><th>Pagamento</th><?php endif; ?><th>Presente</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $i => $r): ?>
            <tr id="u<?= (int) $r['id'] ?>">
                <td><?= $i + 1 ?></td>
                <td><strong><?= e($r['nickname'] ?: $r['name']) ?></strong><?php if ($r['nickname']): ?><br><small><?= e($r['name']) ?></small><?php endif; ?><br><small class="muted"><?= e(User::EXPERIENCE[$r['experience']] ?? '') ?></small></td>
                <td class="nowrap"><?php if ($wa = whatsapp_link($r['phone'])): ?><a href="<?= e($wa) ?>" target="_blank" rel="noopener noreferrer"><?= e($r['phone']) ?></a><?php else: ?><?= e($r['phone']) ?><?php endif; ?></td>
                <td><small><?= e(trim($r['bike_brand'] . ' ' . $r['bike_model'])) ?><?= $r['bike_cc'] ? ' ' . (int) $r['bike_cc'] . 'cc' : '' ?></small></td>
                <td class="blood"><strong><?= e($r['blood_type'] ?: '?') ?></strong></td>
                <td><?= e($r['emergency_name'] ?: '—') ?><br><?php if ($r['emergency_phone']): ?><a href="tel:<?= e(preg_replace('/[^\d+]/', '', $r['emergency_phone'])) ?>"><?= e($r['emergency_phone']) ?></a><?php endif; ?></td>
                <td><small><?php if ($r['waiver_accepted_at']): ?>✔ <?= e(date('d/m H:i', strtotime($r['waiver_accepted_at']))) ?><?= $r['waiver_hash'] !== $waiverHash ? '<br>(versão anterior)' : '' ?><?php else: ?><?= (int) $event['requires_waiver'] ? '✘ não aceito' : '—' ?><?php endif; ?></small></td>
                <?php if ((float) $event['pix_amount'] > 0): ?>
                <td class="nowrap">
                    <span class="tag tag-pay-<?= e($r['payment_status']) ?>"><?= e(Event::PAYMENT[$r['payment_status']] ?? '') ?></span>
                    <?php if ($r['payment_receipt']): ?><br><a class="no-print" href="/admin/comprovantes/<?= e(substr($r['payment_receipt'], 0, 32)) ?>" target="_blank">ver comprovante</a><?php endif; ?>
                    <?php if ($r['payment_status'] !== 'paid'): ?>
                        <form method="post" action="/admin/eventos/<?= (int) $event['id'] ?>/pagamento/<?= (int) $r['id'] ?>" class="no-print"><?= $csrf->field() ?><input type="hidden" name="status" value="paid"><button class="btn btn-sm btn-primary" type="submit">Confirmar pago</button></form>
                    <?php else: ?>
                        <form method="post" action="/admin/eventos/<?= (int) $event['id'] ?>/pagamento/<?= (int) $r['id'] ?>" class="no-print" data-confirm="Desfazer a confirmação do pagamento?"><?= $csrf->field() ?><input type="hidden" name="status" value="pending"><button class="link-btn" type="submit"><small>desfazer</small></button></form>
                    <?php endif; ?>
                </td>
                <?php endif; ?>
                <td class="nowrap">
                    <span class="print-only"><?= $r['attended'] === null ? '☐' : ((int) $r['attended'] ? '☑' : '✘') ?></span>
                    <form method="post" action="/admin/eventos/<?= (int) $event['id'] ?>/presenca/<?= (int) $r['id'] ?>" class="no-print attend-form">
                        <?= $csrf->field() ?>
                        <button class="btn btn-sm<?= (string) $r['attended'] === '1' ? ' btn-primary' : '' ?>" name="attended" value="1" type="submit" title="Compareceu">✔</button>
                        <button class="btn btn-sm<?= (string) $r['attended'] === '0' ? ' btn-danger' : '' ?>" name="attended" value="0" type="submit" title="Faltou">✘</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php else: ?><p class="empty">Ninguém confirmou presença ainda.</p><?php endif; ?>
    <p class="print-only sheet-footer">Impresso em <?= e(date('d/m/Y H:i')) ?> — Ducks Offroad</p>
</div>
