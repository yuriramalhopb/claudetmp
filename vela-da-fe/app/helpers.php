<?php
declare(strict_types=1);

/** Escapa texto para HTML. Use SEMPRE ao imprimir dados nas views. */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

/** Texto com quebras de linha preservadas (já escapado). */
function nl2br_e(?string $value): string
{
    return nl2br(e($value), false);
}

function asset(string $path): string
{
    $file = BASE_PATH . '/public/assets/' . ltrim($path, '/');
    $v = is_file($file) ? substr(md5((string) filemtime($file)), 0, 8) : '1';
    return '/assets/' . ltrim($path, '/') . '?v=' . $v;
}

/** Centavos -> "R$ 5,00" ("Grátis" quando zero e $free = true). */
function money(int $cents, bool $free = false): string
{
    if ($cents <= 0 && $free) {
        return 'Grátis';
    }
    return 'R$ ' . number_format($cents / 100, 2, ',', '.');
}

/** "5,00" / "5.5" / "5" -> 500 / 550 / 500 (centavos). */
function to_cents(string $value): int
{
    $value = str_replace(',', '.', trim($value));
    return (int) round(((float) $value) * 100);
}

/** Centavos -> "5,00" (para campos de formulário). */
function cents_input(int $cents): string
{
    return number_format($cents / 100, 2, ',', '');
}

const MESES = ['', 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
const DIAS_SEMANA = ['domingo', 'segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado'];

function date_br(?string $date, bool $withTime = false): string
{
    if (!$date) {
        return '';
    }
    $ts = strtotime($date);
    if ($ts === false) {
        return '';
    }
    $out = date('d', $ts) . ' de ' . MESES[(int) date('n', $ts)] . ' de ' . date('Y', $ts);
    if ($withTime) {
        $out .= ' às ' . date('H\hi', $ts);
    }
    return $out;
}

function datetime_short(?string $date): string
{
    $ts = $date ? strtotime($date) : false;
    return $ts ? date('d/m/Y H:i', $ts) : '—';
}

/** "2 dias e 3 h" / "5 h 20 min" / "12 min": tempo restante até $expiresAt. */
function remaining_human(?string $expiresAt, ?int $now = null): string
{
    $left = (int) strtotime((string) $expiresAt) - ($now ?? time());
    if ($left <= 0) {
        return 'apagada';
    }
    $days = intdiv($left, 86400);
    $hours = intdiv($left % 86400, 3600);
    $mins = intdiv($left % 3600, 60);
    if ($days > 0) {
        return $days . ($days === 1 ? ' dia' : ' dias') . ($hours > 0 ? ' e ' . $hours . ' h' : '');
    }
    if ($hours > 0) {
        return $hours . ' h' . ($mins > 0 ? ' ' . $mins . ' min' : '');
    }
    return max(1, $mins) . ' min';
}

/** "5 min" / "3 h" / "2 dias": tempo decorrido desde $from. */
function ago_human(?string $from): string
{
    $s = max(0, time() - (int) strtotime((string) $from));
    if ($s < 60) {
        return 'instantes';
    }
    if ($s < 3600) {
        return intdiv($s, 60) . ' min';
    }
    if ($s < 86400) {
        return intdiv($s, 3600) . ' h';
    }
    $d = intdiv($s, 86400);
    return $d . ($d === 1 ? ' dia' : ' dias');
}

/** Duração em horas -> "24 horas", "7 dias". */
function hours_human(int $hours): string
{
    if ($hours >= 24 && $hours % 24 === 0) {
        $d = intdiv($hours, 24);
        return $d . ($d === 1 ? ' dia' : ' dias');
    }
    return $hours . ($hours === 1 ? ' hora' : ' horas');
}

function excerpt(?string $text, int $len = 140): string
{
    $text = trim((string) preg_replace('/\s+/', ' ', (string) $text));
    return mb_strlen($text) > $len ? rtrim(mb_substr($text, 0, $len - 1)) . '…' : $text;
}

function slugify(string $text): string
{
    $t = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
    $t = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '-', $t));
    return trim($t, '-') ?: 'item';
}

function active(string $current, string $prefix): string
{
    $match = $prefix === '/' ? $current === '/' : str_starts_with($current, $prefix);
    return $match ? ' is-active' : '';
}

function whatsapp_link(?string $phone, string $text = ''): ?string
{
    $digits = preg_replace('/\D/', '', (string) $phone);
    if (strlen($digits) < 10) {
        return null;
    }
    if (strlen($digits) <= 11) {
        $digits = '55' . $digits;
    }
    return 'https://wa.me/' . $digits . ($text !== '' ? '?text=' . rawurlencode($text) : '');
}

/** Valor antigo do formulário (após erro de validação) ou valor padrão. */
function old(array $old, string $key, mixed $default = ''): string
{
    return (string) ($old[$key] ?? $default ?? '');
}

function field_error(array $errors, string $key): string
{
    return isset($errors[$key][0]) ? '<p class="field-error">' . e($errors[$key][0]) . '</p>' : '';
}

function selected(mixed $a, mixed $b): string
{
    return (string) $a === (string) $b ? ' selected' : '';
}

function checked(mixed $value): string
{
    return $value ? ' checked' : '';
}

/** Monta query string preservando filtros (para paginação). */
function query_with(array $params): string
{
    $q = http_build_query(array_filter($params, static fn ($v) => $v !== '' && $v !== null && $v !== 0));
    return $q === '' ? '' : '?' . $q;
}

/** Reduz IP/identificador a um hash (LGPD: não guardamos o IP em texto puro nas velas). */
function anon_hash(string $value, string $salt = ''): string
{
    return hash('sha256', $salt . '|' . $value);
}
