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
    ],
    'db' => [
        'driver' => Env::get('DB_DRIVER', 'sqlite'),
        'path' => Env::get('DB_PATH', 'storage/data/ducks.sqlite'),
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
        'enabled' => Env::get('MAIL_ENABLED', false) === true,
        'from' => Env::get('MAIL_FROM', 'nao-responda@ducksoffroad.com.br'),
        'from_name' => Env::get('MAIL_FROM_NAME', 'Ducks Offroad'),
    ],
    'security' => [
        'session_idle_minutes' => (int) Env::get('SESSION_IDLE_MINUTES', 120),
        'login_max_attempts' => (int) Env::get('LOGIN_MAX_ATTEMPTS', 5),
        'login_lock_minutes' => (int) Env::get('LOGIN_LOCK_MINUTES', 15),
        'upload_max_mb' => (int) Env::get('UPLOAD_MAX_MB', 8),
    ],
];
