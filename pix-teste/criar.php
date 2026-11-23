<?php
declare(strict_types=1);
require __DIR__ . '/lib.php';
require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$valor = (float) str_replace(',', '.', str_replace('.', '', (string) ($_POST['valor'] ?? '0')));
$desc = trim((string) ($_POST['descricao'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));

try {
    if ($valor < 0.01 || $valor > 100000) {
        throw new RuntimeException('Informe um valor entre R$ 0,01 e R$ 100.000,00.');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Informe um e-mail válido.');
    }
    $id = criar_pix($valor, mb_substr($desc, 0, 80), $email);
    header('Location: pagamento.php?id=' . $id);
} catch (Throwable $e) {
    header('Location: index.php?erro=' . urlencode($e->getMessage()));
}
