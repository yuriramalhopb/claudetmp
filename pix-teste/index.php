<?php
declare(strict_types=1);
require __DIR__ . '/lib.php';
require_auth();

$erro = $_GET['erro'] ?? '';
$rows = db()->query('SELECT * FROM payments ORDER BY id DESC LIMIT 15')->fetchAll();
$email = env('PAYER_EMAIL');

ob_start(); ?>
<h1>Pix de teste · Mercado Pago</h1>
<?php if (env('MP_ACCESS_TOKEN') === ''): ?>
  <div class="err">Configure o <code>MP_ACCESS_TOKEN</code> no arquivo <code>.env</code> (veja <code>.env.example</code>).</div>
<?php endif; ?>
<?php if ($erro !== ''): ?><div class="err"><?= e($erro) ?></div><?php endif; ?>

<form class="card" method="post" action="criar.php">
  <label for="valor">Valor (R$)</label>
  <input id="valor" name="valor" inputmode="decimal" placeholder="1,00" value="1,00" required>
  <label for="descricao">Descrição</label>
  <input id="descricao" name="descricao" maxlength="80" placeholder="Pix de teste">
  <label for="email">E-mail do pagador</label>
  <input id="email" name="email" type="email" value="<?= e($email) ?>" placeholder="voce@exemplo.com" required>
  <button type="submit">Gerar Pix</button>
</form>

<?php if ($rows): ?>
<div class="card">
  <strong>Últimas cobranças</strong>
  <table>
    <tr><th>Quando</th><th>Valor</th><th>Status</th></tr>
    <?php foreach ($rows as $r): [$lbl, $cls] = status_label($r['status']); ?>
      <tr>
        <td><a href="pagamento.php?id=<?= (int) $r['id'] ?>"><?= e(date('d/m H:i', strtotime($r['created_at']))) ?></a></td>
        <td><?= brl((float) $r['amount']) ?></td>
        <td><span class="badge <?= $cls ?>"><?= e($lbl) ?></span>
          <?php if ($r['status'] === 'approved'): ?> <a href="comprovante.php?id=<?= (int) $r['id'] ?>">comprovante</a><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>
<?php endif;
layout('Pix de teste', ob_get_clean());
