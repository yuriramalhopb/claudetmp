<?php
declare(strict_types=1);

use App\Core\Env;

return [
    'app' => [
        'name' => Env::get('APP_NAME', 'Ducks Offroad'),
        'env' => Env::get('APP_ENV', 'production'),
        'debug' => Env::get('APP_DEBUG', false) === true,
        'url' => rtrim((string) Env::get('APP_URL', 'http://localhost:8000'), '/'),
        'timezone' => Env::get('APP_TIMEZONE', 'America/Sao_Paulo'),
        'key' => (string) Env::get('APP_KEY', ''),
    ],
    'db' => [
        'host' => Env::get('DB_HOST', '127.0.0.1'),
        'port' => Env::get('DB_PORT', 3306),
        'name' => Env::get('DB_NAME', 'ducks_offroad'),
        'user' => Env::get('DB_USER', 'root'),
        'pass' => (string) Env::get('DB_PASS', ''),
    ],
    'admin' => [
        'name' => Env::get('ADMIN_NAME', 'Administrador'),
        'email' => Env::get('ADMIN_EMAIL', ''),
        'password' => (string) Env::get('ADMIN_PASSWORD', ''),
    ],
    'instagram' => [
        'username' => ltrim((string) Env::get('INSTAGRAM_USERNAME', 'ducksoffroad'), '@'),
        'token' => (string) Env::get('INSTAGRAM_ACCESS_TOKEN', ''),
        'sync_minutes' => (int) Env::get('INSTAGRAM_SYNC_MINUTES', 60),
    ],
    'mail' => [
        // log (grava em storage/logs/mail-*.log) | smtp | mail (função mail() do PHP)
        'driver' => Env::get('MAIL_DRIVER', Env::get('MAIL_ENABLED', false) === true ? 'mail' : 'log'),
        'from' => Env::get('MAIL_FROM', 'nao-responda@ducksoffroad.com.br'),
        'from_name' => Env::get('MAIL_FROM_NAME', 'Ducks Offroad'),
        'smtp' => [
            'host' => (string) Env::get('SMTP_HOST', ''),
            'port' => (int) Env::get('SMTP_PORT', 587),
            'encryption' => (string) Env::get('SMTP_ENCRYPTION', 'tls'), // tls (STARTTLS) | ssl | none
            'user' => (string) Env::get('SMTP_USER', ''),
            'pass' => (string) Env::get('SMTP_PASS', ''),
            'timeout' => (int) Env::get('SMTP_TIMEOUT', 15),
        ],
    ],
    'push' => [
        'public_key' => (string) Env::get('VAPID_PUBLIC_KEY', ''),
        'private_key' => (string) Env::get('VAPID_PRIVATE_KEY', ''),
        'subject' => (string) Env::get('VAPID_SUBJECT', 'mailto:contato@ducksoffroad.com.br'),
    ],
    'pix' => [
        'key' => (string) Env::get('PIX_KEY', ''),
        'name' => (string) Env::get('PIX_NAME', 'DUCKS OFFROAD'),
        'city' => (string) Env::get('PIX_CITY', 'SAO PAULO'),
    ],
    'backup' => [
        'dir' => (string) Env::get('BACKUP_DIR', 'storage/backups'),
        'keep_days' => (int) Env::get('BACKUP_KEEP_DAYS', 30),
        'hour' => (int) Env::get('BACKUP_HOUR', 3),
    ],
    'android' => [
        'package' => (string) Env::get('ANDROID_PACKAGE', ''),
        'sha256' => (string) Env::get('ANDROID_SHA256_FINGERPRINTS', ''),
    ],
    'security' => [
        'session_idle_minutes' => (int) Env::get('SESSION_IDLE_MINUTES', 120),
        'login_max_attempts' => (int) Env::get('LOGIN_MAX_ATTEMPTS', 5),
        'login_lock_minutes' => (int) Env::get('LOGIN_LOCK_MINUTES', 15),
        'upload_max_mb' => (int) Env::get('UPLOAD_MAX_MB', 8),
    ],
];
