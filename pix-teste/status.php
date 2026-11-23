<?php
declare(strict_types=1);
require __DIR__ . '/lib.php';
require_auth();
header('Content-Type: application/json');

$id = (int) ($_GET['id'] ?? 0);
$p = buscar($id);
if (!$p) {
    http_response_code(404);
    echo json_encode(['error' => 'not_found']);
    exit;
}
// Só consulta o Mercado Pago enquanto ainda não está num estado final
if (in_array($p['status'], ['pending', 'in_process'], true)) {
    try {
        $p = sincronizar($id) ?? $p;
    } catch (Throwable $e) {
        // mantém o status local; tenta de novo no próximo ciclo
    }
}
echo json_encode(['status' => $p['status'], 'paid_at' => $p['paid_at']]);
