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
// Pago antes de existir o comprovante (ou sem dados salvos): busca de novo no Mercado Pago
if ($p['status'] === 'approved' && empty($p['raw_json'])) {
    try {
        $p = sincronizar($id) ?? $p;
    } catch (Throwable $e) {
    }
}
if ($p['status'] !== 'approved') {
    layout('Comprovante', '<h1>Sem comprovante</h1><p>Esta cobrança ainda não foi paga.</p><p><a href="pagamento.php?id=' . $id . '">Voltar</a></p>');
    exit;
}

$quando = $p['date_approved'] ?: $p['paid_at'];
$linhas = [
    'Valor' => brl((float) $p['amount']),
    'Data e hora' => $quando ? date('d/m/Y H:i:s', strtotime((string) $quando)) : null,
    'Pagador' => $p['payer_name'],
    'Documento do pagador' => $p['payer_doc'] ? mascarar_doc((string) $p['payer_doc']) : null,
    'Descrição' => $p['description'],
    'ID do pagamento (Mercado Pago)' => (string) $p['id'],
    'ID da transação Pix' => $p['e2e_id'],
    'Referência' => $p['external_reference'],
    'Status no Mercado Pago' => $p['status'] . ($p['status_detail'] ? ' (' . $p['status_detail'] . ')' : ''),
    'Valor líquido recebido' => $p['net_amount'] !== null ? brl((float) $p['net_amount']) : null,
];
$raw = $p['raw_json'] ? json_encode(json_decode((string) $p['raw_json']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';

ob_start(); ?>
<style>@media print{.noprint{display:none!important}body{background:#fff;color:#000}.card{border:0}}
dl{margin:0}dt{color:var(--mut);font-size:.85rem;margin-top:10px}dd{margin:0;font-weight:600;word-break:break-all}
pre{white-space:pre-wrap;word-break:break-all;font-size:.75rem}</style>
<h1>Comprovante de pagamento Pix</h1>
<div class="card">
  <p><span class="badge ok">Pagamento confirmado</span></p>
  <dl>
    <?php foreach ($linhas as $k => $v): if ($v === null || $v === '') continue; ?>
      <dt><?= e($k) ?></dt><dd><?= e($v) ?></dd>
    <?php endforeach; ?>
  </dl>
  <p class="mut">Emitido pelo sistema com dados do Mercado Pago em <?= e(date('d/m/Y H:i')) ?>.</p>
</div>
<div class="noprint">
  <button type="button" onclick="window.print()">Imprimir / salvar em PDF</button>
  <p class="center"><a href="pagamento.php?id=<?= $id ?>">← Voltar</a> · <a href="index.php">Início</a></p>
  <?php if ($raw): ?>
    <details class="card" open><summary>Resposta completa do Mercado Pago (dados da transação)</summary><pre><?= e($raw) ?></pre></details>
  <?php endif; ?>
</div>
<?php
layout('Comprovante #' . $id, ob_get_clean());
