<?php
declare(strict_types=1);

use App\Core\Env;

return [
    'app' => [
        'name' => Env::get('APP_NAME', 'Vela da Fé'),
        'env' => Env::get('APP_ENV', 'production'),
        'debug' => Env::get('APP_DEBUG', false) === true,
        'url' => rtrim((string) Env::get('APP_URL', 'http://localhost:8000'), '/'),
        'timezone' => Env::get('APP_TIMEZONE', 'America/Sao_Paulo'),
    ],
    'db' => [
        // sqlite (um arquivo, zero configuração) | mysql (MySQL 5.7+/8 ou MariaDB 10.3+)
        'driver' => Env::get('DB_DRIVER', 'sqlite'),
        'sqlite_path' => (string) Env::get('DB_SQLITE_PATH', dirname(__DIR__) . '/storage/vela.sqlite'),
        'host' => Env::get('DB_HOST', '127.0.0.1'),
        'port' => Env::get('DB_PORT', 3306),
        'name' => Env::get('DB_NAME', 'vela_da_fe'),
        'user' => Env::get('DB_USER', 'root'),
        'pass' => (string) Env::get('DB_PASS', ''),
    ],
    'admin' => [
        'name' => Env::get('ADMIN_NAME', 'Administrador'),
        'email' => Env::get('ADMIN_EMAIL', ''),
        'password' => (string) Env::get('ADMIN_PASSWORD', ''),
    ],
    'mail' => [
        // log (grava em storage/logs/mail-*.log) | smtp | mail (função mail() do PHP)
        'driver' => Env::get('MAIL_DRIVER', 'log'),
        'from' => Env::get('MAIL_FROM', 'nao-responda@example.com'),
        'from_name' => Env::get('MAIL_FROM_NAME', Env::get('APP_NAME', 'Vela da Fé')),
        'smtp' => [
            'host' => (string) Env::get('SMTP_HOST', ''),
            'port' => (int) Env::get('SMTP_PORT', 587),
            'encryption' => (string) Env::get('SMTP_ENCRYPTION', 'tls'), // tls (STARTTLS) | ssl | none
            'user' => (string) Env::get('SMTP_USER', ''),
            'pass' => (string) Env::get('SMTP_PASS', ''),
            'timeout' => (int) Env::get('SMTP_TIMEOUT', 15),
        ],
    ],
    // Valores padrão do Pix (podem ser alterados depois em Admin > Configurações)
    'pix' => [
        'key' => (string) Env::get('PIX_KEY', ''),
        'name' => (string) Env::get('PIX_NAME', ''),
        'city' => (string) Env::get('PIX_CITY', ''),
    ],
    'backup' => [
        'dir' => (string) Env::get('BACKUP_DIR', 'storage/backups'),
        'keep_days' => (int) Env::get('BACKUP_KEEP_DAYS', 30),
    ],
    'security' => [
        'session_idle_minutes' => (int) Env::get('SESSION_IDLE_MINUTES', 120),
        'login_max_attempts' => (int) Env::get('LOGIN_MAX_ATTEMPTS', 5),
        'login_lock_minutes' => (int) Env::get('LOGIN_LOCK_MINUTES', 15),
    ],
];
