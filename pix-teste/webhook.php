<?php
declare(strict_types=1);
require __DIR__ . '/lib.php';
// Sem require_auth(): quem chama é o Mercado Pago. A segurança vem de (1) assinatura, se configurada,
// e (2) sempre reconsultar o pagamento na API oficial, nunca confiar no corpo recebido.

$raw = file_get_contents('php://input') ?: '';
$data = json_decode($raw, true) ?: [];
$id = (int) ($data['data']['id'] ?? $_GET['data_id'] ?? $_GET['id'] ?? 0);
$type = (string) ($data['type'] ?? $_GET['type'] ?? $_GET['topic'] ?? '');

$secret = env('MP_WEBHOOK_SECRET');
if ($secret !== '') {
    $sig = $_SERVER['HTTP_X_SIGNATURE'] ?? '';
    $parts = [];
    foreach (explode(',', $sig) as $kv) {
        $kv = explode('=', trim($kv), 2);
        if (count($kv) === 2) {
            $parts[$kv[0]] = $kv[1];
        }
    }
    $dataId = strtolower((string) ($_GET['data_id'] ?? $id));
    $manifest = 'id:' . $dataId . ';request-id:' . ($_SERVER['HTTP_X_REQUEST_ID'] ?? '') . ';ts:' . ($parts['ts'] ?? '') . ';';
    if (!hash_equals(hash_hmac('sha256', $manifest, $secret), $parts['v1'] ?? '')) {
        http_response_code(401);
        exit('assinatura inválida');
    }
}

if ($id > 0 && ($type === '' || $type === 'payment') && buscar($id)) {
    try {
        sincronizar($id);
    } catch (Throwable $e) {
        error_log('webhook pix-teste: ' . $e->getMessage());
        http_response_code(500); // o Mercado Pago tenta de novo
        exit;
    }
}
http_response_code(200);
echo 'ok';
