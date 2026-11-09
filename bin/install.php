<?php
declare(strict_types=1);

/**
 * Instalação/atualização:
 *  - gera APP_KEY e as chaves de notificação (VAPID) no .env, se faltarem
 *  - cria/atualiza as tabelas (migrações)
 *  - cria o primeiro administrador
 * Uso: php bin/install.php
 */
if (PHP_SAPI !== 'cli') {
    exit("Somente via linha de comando.\n");
}

$base = dirname(__DIR__);
$envFile = $base . '/.env';
if (!is_file($envFile)) {
    if (!copy($base . '/.env.example', $envFile)) {
        fwrite(STDERR, "✘ Crie o arquivo .env a partir do .env.example.\n");
        exit(1);
    }
    chmod($envFile, 0600);
    echo "✔ .env criado a partir do .env.example — revise os dados do banco e rode de novo.\n";
    exit(0);
}

// --- chaves de segurança geradas automaticamente ---
require $base . '/app/Core/Crypto.php';
require $base . '/app/Services/WebPush.php';
$env = (string) file_get_contents($envFile);
$setEnv = static function (string $key, string $value) use (&$env): void {
    if (preg_match('/^' . $key . '=.*$/m', $env)) {
        $env = (string) preg_replace('/^' . $key . '=.*$/m', $key . '=' . $value, $env);
    } else {
        $env = rtrim($env) . "\n{$key}={$value}\n";
    }
};
$changed = false;
if (!preg_match('/^APP_KEY=\S+/m', $env)) {
    $setEnv('APP_KEY', App\Core\Crypto::generateKey());
    echo "✔ APP_KEY gerada (guarde o .env com segurança: sem ela o 2FA dos admins precisa ser refeito).\n";
    $changed = true;
}
if (!preg_match('/^VAPID_PRIVATE_KEY=\S+/m', $env)) {
    $keys = App\Services\WebPush::generateVapidKeys();
    $setEnv('VAPID_PRIVATE_KEY', $keys['private_b64']);
    echo "✔ Chaves de notificação push (VAPID) geradas.\n";
    $changed = true;
}
if ($changed) {
    file_put_contents($envFile, $env, LOCK_EX);
    chmod($envFile, 0600);
}

$config = require $base . '/app/bootstrap.php';

use App\Core\Database;
use App\Core\Validator;
use App\Models\User;

$db = new Database($config->get('db'));
$applied = $db->migrate(BASE_PATH . '/database');
echo "✔ Tabelas criadas/atualizadas." . ($applied ? ' Migrações: ' . implode(', ', $applied) : '') . "\n";

$users = new User($db);
if ($users->countAdmins() > 0) {
    echo "✔ Já existe administrador.\n";
} else {
    $ask = static function (string $label, bool $hidden = false): string {
        echo $label;
        if ($hidden && DIRECTORY_SEPARATOR === '/' && posix_isatty(STDIN)) {
            system('stty -echo');
            $v = trim((string) fgets(STDIN));
            system('stty echo');
            echo "\n";
            return $v;
        }
        return trim((string) fgets(STDIN));
    };
    echo "\nCriar o primeiro administrador:\n";
    $name = $ask('Nome: ');
    $email = $ask('E-mail: ');
    $password = $ask('Senha (8+ caracteres, letras e números): ', true);
    if ($ask('Repita a senha: ', true) !== $password) {
        fwrite(STDERR, "✘ As senhas não conferem. Rode o instalador de novo.\n");
        exit(1);
    }
    $v = Validator::make(compact('name', 'email', 'password'), [
        'name' => 'required|min:3|max:120',
        'email' => 'required|email',
        'password' => 'required|password',
    ]);
    if ($v->fails()) {
        fwrite(STDERR, '✘ ' . $v->firstError() . "\n");
        exit(1);
    }
    if ($users->emailExists($email)) {
        fwrite(STDERR, "✘ E-mail já cadastrado.\n");
        exit(1);
    }
    $users->create(['name' => $name, 'email' => $email], $password, 'approved', 'admin');
    echo "✔ Administrador criado! Acesse /entrar e depois /admin (o 2FA será configurado no primeiro acesso).\n";
}

echo "\nPróximo passo: agende o cron (a cada 5 minutos):\n  */5 * * * * php " . BASE_PATH . "/bin/cron.php >> " . BASE_PATH . "/storage/logs/cron.log 2>&1\n";
