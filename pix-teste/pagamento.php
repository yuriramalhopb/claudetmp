<?php
declare(strict_types=1);
require __DIR__ . '/lib.php';
require_auth();

$id = (int) ($_GET['id'] ?? 0);
$p = buscar($id);
if (!$p) {
    http_response_code(404);
    layout('Não encontrado', '<h1>Cobrança não encontrada</h1><p><a href="index.php">Voltar</a></p>');
    exit;
}
[$lbl, $cls] = status_label($p['status']);

ob_start(); ?>
<h1>Pix <?= brl((float) $p['amount']) ?></h1>
<div class="card center">
  <p><span id="badge" class="badge <?= $cls ?>"><?= e($lbl) ?></span></p>
  <div id="qr" <?= $p['status'] === 'approved' ? 'hidden' : '' ?>>
    <?php if ($p['qr_code_base64']): ?>
      <img src="data:image/png;base64,<?= e($p['qr_code_base64']) ?>" alt="QR Code Pix" width="240" height="240">
    <?php endif; ?>
    <label for="code">Pix copia e cola</label>
    <textarea id="code" rows="4" readonly><?= e($p['qr_code']) ?></textarea>
    <button type="button" id="copy">Copiar código</button>
    <p class="mut">Expira em <?= e(date('d/m H:i', strtotime((string) $p['expires_at']))) ?></p>
  </div>
  <p id="paid" class="mut" <?= $p['status'] === 'approved' ? '' : 'hidden' ?>><?= $p['status'] === 'approved' ? '✅ Pagamento confirmado.' : '' ?></p>
  <p id="rec" <?= $p['status'] === 'approved' ? '' : 'hidden' ?>><a href="comprovante.php?id=<?= (int) $p['id'] ?>">Ver comprovante</a></p>
</div>
<p class="center"><a href="index.php">← Gerar outro</a></p>

<script>
const id = <?= $id ?>;
const labels = {approved:['Pago','ok'],pending:['Aguardando pagamento','wait'],in_process:['Aguardando pagamento','wait'],
  cancelled:['Expirado/cancelado','bad'],expired:['Expirado/cancelado','bad'],rejected:['Recusado','bad']};
document.getElementById('copy').onclick = async (ev) => {
  const t = document.getElementById('code');
  try { await navigator.clipboard.writeText(t.value); } catch (e) { t.select(); document.execCommand('copy'); }
  ev.target.textContent = 'Copiado!';
  setTimeout(() => ev.target.textContent = 'Copiar código', 1500);
};
async function poll() {
  try {
    const r = await fetch('status.php?id=' + id, {cache: 'no-store'});
    const d = await r.json();
    const [txt, cls] = labels[d.status] || [d.status, 'wait'];
    const b = document.getElementById('badge');
    b.textContent = txt; b.className = 'badge ' + cls;
    if (d.status === 'approved') {
      document.getElementById('qr').hidden = true;
      const p = document.getElementById('paid');
      document.getElementById('rec').hidden = false;
      p.hidden = false; p.textContent = '✅ Pagamento confirmado' + (d.paid_at ? ' em ' + d.paid_at : '') + '.';
      return;
    }
    if (['cancelled','expired','rejected'].includes(d.status)) { document.getElementById('qr').hidden = true; return; }
  } catch (e) {}
  setTimeout(poll, 4000);
}
poll();
</script>
<?php
layout('Pix ' . brl((float) $p['amount']), ob_get_clean());
