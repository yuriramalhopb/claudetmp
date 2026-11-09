<?php
declare(strict_types=1);

/**
 * Redefine a senha de um usuário pelo servidor (útil se o e-mail ainda não estiver configurado).
 * Também libera bloqueios por tentativas erradas.
 * Uso: sudo -u www-data php bin/reset-password.php [email]
 */
if (PHP_SAPI !== 'cli') {
    exit("Somente via linha de comando.\n");
}
$config = require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\App;
use App\Core\Validator;
use App\Models\AuditLog;
use App\Models\User;

$app = new App($config, BASE_PATH);
$users = $app->model(User::class);

$hidden = static function (string $label): string {
    echo $label;
    $tty = DIRECTORY_SEPARATOR === '/' && function_exists('posix_isatty') && posix_isatty(STDIN);
    if ($tty) {
        system('stty -echo');
    }
    $v = trim((string) fgets(STDIN));
    if ($tty) {
        system('stty echo');
        echo "\n";
    }
    return $v;
};

$email = $argv[1] ?? '';
if ($email === '') {
    echo 'E-mail do usuário: ';
    $email = trim((string) fgets(STDIN));
}
$user = $users->findByEmail($email);
if ($user === null) {
    fwrite(STDERR, "✘ Nenhum usuário com o e-mail {$email} neste banco ({$config->get('db.name')}).\n");
    exit(1);
}
echo "Usuário: {$user['name']} — situação: {$user['status']}, perfil: {$user['role']}\n";

$password = $hidden('Nova senha (8+ caracteres, letras e números): ');
$confirm = $hidden('Repita a nova senha: ');
if ($password !== $confirm) {
    fwrite(STDERR, "✘ As senhas não conferem. Nada foi alterado.\n");
    exit(1);
}
$v = Validator::make(['password' => $password], ['password' => 'required|password']);
if ($v->fails()) {
    fwrite(STDERR, '✘ ' . $v->firstError() . "\n");
    exit(1);
}

$users->updatePassword((int) $user['id'], $password); // também encerra sessões abertas
$app->db->execute('DELETE FROM rate_limits');
$fresh = $users->find((int) $user['id']);
if (!password_verify($password, (string) $fresh['password_hash'])) {
    fwrite(STDERR, "✘ A senha não pôde ser verificada após salvar.\n");
    exit(1);
}
$app->model(AuditLog::class)->record((int) $user['id'], 'senha_redefinida_cli', 'via bin/reset-password.php', 'cli');
echo "✔ Senha alterada e bloqueios de login liberados. Entre em /entrar.\n";
if ($fresh['status'] !== 'approved') {
    echo "⚠ Atenção: a situação deste usuário é '{$fresh['status']}'.\n";
}
