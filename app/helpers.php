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

/** Converte links http(s) em <a> com segurança (texto escapado antes). */
function linkify(?string $value): string
{
    $html = nl2br_e($value);
    return (string) preg_replace_callback(
        '#\bhttps?://[^\s<]{3,300}#i',
        static fn ($m) => '<a href="' . $m[0] . '" target="_blank" rel="noopener noreferrer nofollow">' . $m[0] . '</a>',
        $html
    );
}

function asset(string $path): string
{
    $file = BASE_PATH . '/public/assets/' . ltrim($path, '/');
    $v = is_file($file) ? substr(md5((string) filemtime($file)), 0, 8) : '1';
    return '/assets/' . ltrim($path, '/') . '?v=' . $v;
}

function upload_url(string $folder, ?string $file, bool $thumb = false): ?string
{
    if ($file === null || $file === '' || !preg_match('/^[a-f0-9]{32}\.jpg$/', $file)) {
        return null;
    }
    return '/uploads/' . $folder . '/' . ($thumb ? 'thumbs/' : '') . $file;
}

function avatar_url(?array $user): string
{
    return upload_url('avatars', $user['photo'] ?? null) ?? '/assets/img/avatar.svg';
}

function display_name(array $user): string
{
    return (string) (($user['nickname'] ?? '') !== '' && $user['nickname'] !== null ? $user['nickname'] : $user['name']);
}

const MESES = ['', 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
const MESES_CURTOS = ['', 'JAN', 'FEV', 'MAR', 'ABR', 'MAI', 'JUN', 'JUL', 'AGO', 'SET', 'OUT', 'NOV', 'DEZ'];
const DIAS_SEMANA = ['domingo', 'segunda', 'terça', 'quarta', 'quinta', 'sexta', 'sábado'];

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
    if ($withTime && date('H:i', $ts) !== '00:00') {
        $out .= ' às ' . date('H\hi', $ts);
    }
    return $out;
}

function weekday_br(string $date): string
{
    return DIAS_SEMANA[(int) date('w', (int) strtotime($date))];
}

function month_short(string $date): string
{
    return MESES_CURTOS[(int) date('n', (int) strtotime($date))];
}

function month_year_br(string $date): string
{
    $ts = (int) strtotime($date);
    return ucfirst(MESES[(int) date('n', $ts)]) . ' ' . date('Y', $ts);
}

function excerpt(?string $text, int $len = 140): string
{
    $text = trim((string) preg_replace('/\s+/', ' ', (string) $text));
    return mb_strlen($text) > $len ? mb_substr($text, 0, $len - 1) . '…' : $text;
}

/** Valor para <input type="datetime-local">. */
function dt_input(?string $value): string
{
    return $value ? date('Y-m-d\TH:i', (int) strtotime($value)) : '';
}

function active(string $current, string $prefix): string
{
    $match = $prefix === '/' ? $current === '/' : str_starts_with($current, $prefix);
    return $match ? ' is-active' : '';
}

function whatsapp_link(?string $phone): ?string
{
    $digits = preg_replace('/\D/', '', (string) $phone);
    if (strlen($digits) < 10) {
        return null;
    }
    if (strlen($digits) <= 11) {
        $digits = '55' . $digits;
    }
    return 'https://wa.me/' . $digits;
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
