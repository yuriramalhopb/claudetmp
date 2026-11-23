<?php
declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');

function env(string $key, string $default = ''): string
{
    static $vars = null;
    if ($vars === null) {
        $vars = [];
        $file = __DIR__ . '/.env';
        if (is_file($file)) {
            foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                    continue;
                }
                [$k, $v] = explode('=', $line, 2);
                $vars[trim($k)] = trim(trim($v), "\"'");
            }
        }
    }
    $v = $vars[$key] ?? getenv($key);
    return ($v === false || $v === null || $v === '') ? $default : (string) $v;
}

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function brl(float $v): string
{
    return 'R$ ' . number_format($v, 2, ',', '.');
}

/** Protege as páginas com senha (HTTP Basic) quando APP_PASSWORD estiver definida. */
function require_auth(): void
{
    $pass = env('APP_PASSWORD');
    if ($pass === '') {
        return;
    }
    $given = $_SERVER['PHP_AUTH_PW'] ?? '';
    if (!hash_equals($pass, $given)) {
        header('WWW-Authenticate: Basic realm="Pix teste"');
        http_response_code(401);
        exit('Acesso restrito.');
    }
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('sqlite:' . __DIR__ . '/data/pix.sqlite');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('CREATE TABLE IF NOT EXISTS payments (
            id INTEGER PRIMARY KEY,            -- id do pagamento no Mercado Pago
            external_reference TEXT NOT NULL,
            amount REAL NOT NULL,
            description TEXT NOT NULL DEFAULT "",
            status TEXT NOT NULL,
            status_detail TEXT,
            qr_code TEXT,
            qr_code_base64 TEXT,
            ticket_url TEXT,
            expires_at TEXT,
            created_at TEXT NOT NULL,
            paid_at TEXT
        )');
    }
    return $pdo;
}

function mp_base(): string
{
    return rtrim(env('MP_API_BASE', 'https://api.mercadopago.com'), '/');
}

/** Chamada à API do Mercado Pago. Lança RuntimeException com a mensagem do erro. */
function mp_request(string $method, string $path, ?array $body = null, ?string $idempotencyKey = null): array
{
    $token = env('MP_ACCESS_TOKEN');
    if ($token === '') {
        throw new RuntimeException('MP_ACCESS_TOKEN não configurado no arquivo .env');
    }
    $headers = ['Authorization: Bearer ' . $token, 'Content-Type: application/json', 'Accept: application/json'];
    if ($idempotencyKey !== null) {
        $headers[] = 'X-Idempotency-Key: ' . $idempotencyKey;
    }
    $ch = curl_init(mp_base() . $path);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
    $raw = curl_exec($ch);
    if ($raw === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('Falha de conexão com o Mercado Pago: ' . $err);
    }
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    $data = json_decode((string) $raw, true);
    if (!is_array($data)) {
        throw new RuntimeException("Resposta inválida do Mercado Pago (HTTP $code).");
    }
    if ($code >= 400) {
        $msg = $data['message'] ?? 'erro desconhecido';
        if (!empty($data['cause'][0]['description'])) {
            $msg .= ' — ' . $data['cause'][0]['description'];
        }
        throw new RuntimeException("Mercado Pago (HTTP $code): $msg");
    }
    return $data;
}

function uuid4(): string
{
    $d = random_bytes(16);
    $d[6] = chr((ord($d[6]) & 0x0f) | 0x40);
    $d[8] = chr((ord($d[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
}

/** Cria a cobrança Pix no Mercado Pago e grava no banco local. Retorna o id. */
function criar_pix(float $amount, string $description, string $payerEmail): int
{
    $ref = 'T' . date('ymdHis') . strtoupper(bin2hex(random_bytes(2)));
    $minutes = max(5, (int) env('PIX_EXPIRA_MIN', '30'));
    $expires = (new DateTimeImmutable("+{$minutes} minutes"))->format('Y-m-d\TH:i:s.000P');

    $body = [
        'transaction_amount' => round($amount, 2),
        'description' => $description !== '' ? $description : 'Pix de teste',
        'payment_method_id' => 'pix',
        'external_reference' => $ref,
        'date_of_expiration' => $expires,
        'payer' => ['email' => $payerEmail],
    ];
    $appUrl = rtrim(env('APP_URL'), '/');
    if ($appUrl !== '' && str_starts_with($appUrl, 'https://')) {
        $body['notification_url'] = $appUrl . '/webhook.php';
    }

    $p = mp_request('POST', '/v1/payments', $body, uuid4());
    $td = $p['point_of_interaction']['transaction_data'] ?? [];
    if (empty($td['qr_code'])) {
        throw new RuntimeException('O Mercado Pago não devolveu o código Pix. Sua conta tem Pix habilitado (chave Pix cadastrada)?');
    }

    db()->prepare('INSERT OR REPLACE INTO payments
        (id, external_reference, amount, description, status, status_detail, qr_code, qr_code_base64, ticket_url, expires_at, created_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([
            (int) $p['id'], $ref, $amount, $body['description'], (string) $p['status'], $p['status_detail'] ?? null,
            $td['qr_code'], $td['qr_code_base64'] ?? null, $td['ticket_url'] ?? null,
            $p['date_of_expiration'] ?? $expires, date('Y-m-d H:i:s'),
        ]);
    return (int) $p['id'];
}

/** Consulta o pagamento no Mercado Pago e atualiza o status local. Retorna a linha atualizada. */
function sincronizar(int $id): ?array
{
    $p = mp_request('GET', '/v1/payments/' . $id);
    $status = (string) ($p['status'] ?? 'unknown');
    $paidAt = $status === 'approved' ? date('Y-m-d H:i:s') : null;
    db()->prepare('UPDATE payments SET status = ?, status_detail = ?, paid_at = COALESCE(paid_at, ?) WHERE id = ?')
        ->execute([$status, $p['status_detail'] ?? null, $paidAt, $id]);
    return buscar($id);
}

function buscar(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM payments WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function status_label(string $s): array
{
    return match ($s) {
        'approved' => ['Pago', 'ok'],
        'pending', 'in_process' => ['Aguardando pagamento', 'wait'],
        'cancelled', 'expired' => ['Expirado/cancelado', 'bad'],
        'rejected' => ['Recusado', 'bad'],
        'refunded', 'charged_back' => ['Estornado', 'bad'],
        default => [$s, 'wait'],
    };
}

function layout(string $title, string $body): void
{
    echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8">',
        '<meta name="viewport" content="width=device-width,initial-scale=1">',
        '<title>', e($title), '</title><style>',
        ':root{--bg:#f5f6f8;--card:#fff;--tx:#1b1f24;--mut:#6b7280;--pri:#009ee3;--ok:#16a34a;--bad:#dc2626;--wait:#d97706;--bd:#e5e7eb}',
        '@media(prefers-color-scheme:dark){:root{--bg:#111418;--card:#1b2026;--tx:#e8eaed;--mut:#9aa3ad;--bd:#2c343c}}',
        '*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--tx);font:16px/1.5 system-ui,sans-serif}',
        'main{max-width:560px;margin:0 auto;padding:16px}h1{font-size:1.4rem;margin:.5rem 0 1rem}',
        '.card{background:var(--card);border:1px solid var(--bd);border-radius:12px;padding:16px;margin-bottom:16px}',
        'label{display:block;font-size:.9rem;color:var(--mut);margin:.6rem 0 .2rem}',
        'input,textarea,button{font:inherit;width:100%;padding:10px;border-radius:8px;border:1px solid var(--bd);background:var(--bg);color:var(--tx)}',
        'button{background:var(--pri);border:0;color:#fff;font-weight:600;cursor:pointer;margin-top:12px}',
        'button.sec{background:var(--bg);color:var(--tx);border:1px solid var(--bd)}',
        '.badge{display:inline-block;padding:2px 10px;border-radius:99px;font-size:.85rem;font-weight:600;color:#fff}',
        '.badge.ok{background:var(--ok)}.badge.bad{background:var(--bad)}.badge.wait{background:var(--wait)}',
        '.err{background:#fee2e2;color:#991b1b;padding:10px;border-radius:8px;margin-bottom:12px}',
        'table{width:100%;border-collapse:collapse;font-size:.92rem}td,th{padding:8px 4px;border-bottom:1px solid var(--bd);text-align:left}',
        'a{color:var(--pri)}.mut{color:var(--mut);font-size:.9rem}.center{text-align:center}',
        '</style></head><body><main>', $body, '</main></body></html>';
}
